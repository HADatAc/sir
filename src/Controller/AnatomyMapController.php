<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides CRUD and lookup endpoints for anatomy coordinate mappings.
 */
class AnatomyMapController extends ControllerBase {

  private const TABLE = 'sir_anatomy_mapping';

  /**
   * Lists mappings. By default returns enabled records only.
   */
  public function listMappings(Request $request): JsonResponse {
    if (!$this->mappingTableExists()) {
      return new JsonResponse([
        'isSuccessful' => TRUE,
        'body' => [],
      ]);
    }

    $all = (string) $request->query->get('all', '0') === '1';
    $is_admin = $this->currentUser()->hasPermission('administer sir anatomy mappings');

    $query = \Drupal::database()->select(self::TABLE, 'm')
      ->fields('m', [
        'id',
        'label',
        'uberon_uri',
        'x_min',
        'x_max',
        'y_min',
        'y_max',
        'description',
        'status',
        'weight',
        'changed',
      ])
      ->orderBy('weight', 'ASC')
      ->orderBy('id', 'ASC');

    if (!$all || !$is_admin) {
      $query->condition('status', 1);
    }

    $rows = $query->execute()->fetchAllAssoc('id');
    $body = [];
    foreach ($rows as $row) {
      $body[] = $this->normalizeRow((array) $row);
    }

    return new JsonResponse([
      'isSuccessful' => TRUE,
      'body' => $body,
    ]);
  }

  /**
   * Resolves a normalized coordinate x/y (0-100) to a mapped UBERON URI.
   */
  public function resolveByCoordinate(Request $request): JsonResponse {
    if (!$this->mappingTableExists()) {
      return new JsonResponse([
        'isSuccessful' => TRUE,
        'body' => NULL,
        'matches' => [],
      ]);
    }

    $x = (float) $request->query->get('x', -1);
    $y = (float) $request->query->get('y', -1);

    if ($x < 0 || $x > 100 || $y < 0 || $y > 100) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Coordinates must be normalized percentages between 0 and 100.',
      ], 400);
    }

    $rows = \Drupal::database()->select(self::TABLE, 'm')
      ->fields('m', [
        'id',
        'label',
        'uberon_uri',
        'x_min',
        'x_max',
        'y_min',
        'y_max',
        'description',
        'status',
        'weight',
      ])
      ->condition('status', 1)
      ->condition('x_min', $x, '<=')
      ->condition('x_max', $x, '>=')
      ->condition('y_min', $y, '<=')
      ->condition('y_max', $y, '>=')
      ->execute()
      ->fetchAll();

    if (empty($rows)) {
      return new JsonResponse([
        'isSuccessful' => TRUE,
        'body' => NULL,
        'matches' => [],
      ]);
    }

    // Pick the smallest region for specificity.
    usort($rows, static function ($a, $b) {
      $area_a = ((float) $a->x_max - (float) $a->x_min) * ((float) $a->y_max - (float) $a->y_min);
      $area_b = ((float) $b->x_max - (float) $b->x_min) * ((float) $b->y_max - (float) $b->y_min);
      if ($area_a === $area_b) {
        return ((int) $a->weight) <=> ((int) $b->weight);
      }
      return $area_a <=> $area_b;
    });

    $matches = [];
    foreach ($rows as $row) {
      $matches[] = $this->normalizeRow((array) $row);
    }

    return new JsonResponse([
      'isSuccessful' => TRUE,
      'body' => $matches[0],
      'matches' => $matches,
    ]);
  }

  /**
   * Proxies instrument lookups by anatomy to HASCOAPI.
   */
  public function listInstrumentsByAnatomy(string $uberon, Request $request): JsonResponse {
    $uberon = trim($uberon);
    if ($uberon === '') {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'No UBERON URI token has been provided.',
      ], 400);
    }

    $api = \Drupal::service('rep.api_connector');
    $uberon_path = str_starts_with($uberon, 'b64:') ? $uberon : rawurlencode($uberon);
    $endpoint = '/hascoapi/api/instrument/byanatomy/' . $uberon_path;

    $organization_uri = trim((string) $request->query->get('organizationUri', ''));
    if ($organization_uri !== '') {
      $endpoint .= '?organizationUri=' . rawurlencode($organization_uri);
    }

    $url = rtrim((string) $api->getApiUrl(), '/') . $endpoint;
    $raw = $api->perform_http_request('GET', $url, $api->getHeader());
    if ($raw === NULL) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Failed to fetch instruments by anatomy from HASCOAPI.',
        'details' => (string) $api->getErrorMessage(),
      ], 502);
    }

    $decoded = json_decode((string) $raw, TRUE);
    if (!is_array($decoded)) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Invalid JSON response from HASCOAPI.',
      ], 502);
    }

    return new JsonResponse($decoded, 200);
  }

  /**
   * Creates or updates a mapping.
   */
  public function saveMapping(Request $request): JsonResponse {
    if (!$this->mappingTableExists()) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Anatomy mapping storage is not initialized.',
      ], 503);
    }

    if (!$this->currentUser()->hasPermission('administer sir anatomy mappings')) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Permission denied.',
      ], 403);
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Invalid JSON payload.',
      ], 400);
    }

    $normalized = $this->normalizePayload($payload);
    if (!empty($normalized['error'])) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => $normalized['error'],
      ], 400);
    }

    $now = \Drupal::time()->getRequestTime();
    $uid = (int) $this->currentUser()->id();

    $db = \Drupal::database();
    if (!empty($normalized['id'])) {
      $id = (int) $normalized['id'];
      $exists = $db->select(self::TABLE, 'm')
        ->fields('m', ['id'])
        ->condition('id', $id)
        ->execute()
        ->fetchField();

      if (!$exists) {
        return new JsonResponse([
          'isSuccessful' => FALSE,
          'message' => 'Mapping id not found.',
        ], 404);
      }

      $db->update(self::TABLE)
        ->fields([
          'label' => $normalized['label'],
          'uberon_uri' => $normalized['uberon_uri'],
          'x_min' => $normalized['x_min'],
          'x_max' => $normalized['x_max'],
          'y_min' => $normalized['y_min'],
          'y_max' => $normalized['y_max'],
          'description' => $normalized['description'],
          'status' => $normalized['status'],
          'weight' => $normalized['weight'],
          'changed' => $now,
        ])
        ->condition('id', $id)
        ->execute();

      return new JsonResponse([
        'isSuccessful' => TRUE,
        'message' => 'Mapping updated.',
        'body' => ['id' => $id],
      ]);
    }

    $id = $db->insert(self::TABLE)
      ->fields([
        'label' => $normalized['label'],
        'uberon_uri' => $normalized['uberon_uri'],
        'x_min' => $normalized['x_min'],
        'x_max' => $normalized['x_max'],
        'y_min' => $normalized['y_min'],
        'y_max' => $normalized['y_max'],
        'description' => $normalized['description'],
        'status' => $normalized['status'],
        'weight' => $normalized['weight'],
        'created' => $now,
        'changed' => $now,
        'uid' => $uid,
      ])
      ->execute();

    return new JsonResponse([
      'isSuccessful' => TRUE,
      'message' => 'Mapping created.',
      'body' => ['id' => (int) $id],
    ]);
  }

  /**
   * Deletes a mapping by id.
   */
  public function deleteMapping(int $id): JsonResponse {
    if (!$this->mappingTableExists()) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Anatomy mapping storage is not initialized.',
      ], 503);
    }

    if (!$this->currentUser()->hasPermission('administer sir anatomy mappings')) {
      return new JsonResponse([
        'isSuccessful' => FALSE,
        'message' => 'Permission denied.',
      ], 403);
    }

    $deleted = \Drupal::database()->delete(self::TABLE)
      ->condition('id', $id)
      ->execute();

    return new JsonResponse([
      'isSuccessful' => (bool) $deleted,
      'message' => $deleted ? 'Mapping deleted.' : 'Mapping id not found.',
    ], $deleted ? 200 : 404);
  }

  /**
   * Normalizes and validates request payload.
   */
  private function normalizePayload(array $payload): array {
    $result = [
      'id' => isset($payload['id']) && $payload['id'] !== '' ? (int) $payload['id'] : NULL,
      'label' => trim((string) ($payload['label'] ?? '')),
      'uberon_uri' => trim((string) ($payload['uberon_uri'] ?? '')),
      'x_min' => (float) ($payload['x_min'] ?? -1),
      'x_max' => (float) ($payload['x_max'] ?? -1),
      'y_min' => (float) ($payload['y_min'] ?? -1),
      'y_max' => (float) ($payload['y_max'] ?? -1),
      'description' => trim((string) ($payload['description'] ?? '')),
      'status' => ((int) ($payload['status'] ?? 1)) ? 1 : 0,
      'weight' => (int) ($payload['weight'] ?? 0),
    ];

    if ($result['uberon_uri'] === '') {
      $result['error'] = 'UBERON URI is required.';
      return $result;
    }

    // Accept registered namespace abbreviations (e.g., obo:UBERON_0000465)
    // and normalize to full URI internally.
    $result['uberon_uri'] = $this->expandRegisteredCurie($result['uberon_uri']);

    if (!preg_match('/^https?:\/\//', $result['uberon_uri'])) {
      $result['error'] = 'UBERON URI must be an absolute http(s) URI.';
      return $result;
    }

    foreach (['x_min', 'x_max', 'y_min', 'y_max'] as $key) {
      if ($result[$key] < 0 || $result[$key] > 100) {
        $result['error'] = 'Coordinate values must be between 0 and 100.';
        return $result;
      }
    }

    if ($result['x_min'] > $result['x_max'] || $result['y_min'] > $result['y_max']) {
      $result['error'] = 'Minimum coordinates cannot be greater than maximum coordinates.';
      return $result;
    }

    return $result;
  }

  /**
   * Expands a registered CURIE/prefixed URI to a full URI when possible.
   */
  private function expandRegisteredCurie(string $value): string {
    $uri = trim($value);
    if ($uri === '' || preg_match('/^https?:\/\//i', $uri)) {
      return $uri;
    }

    // Fast path using existing utility.
    $expanded = trim((string) Utils::plainUri($uri));
    if (preg_match('/^https?:\/\//i', $expanded)) {
      return $expanded;
    }

    if (!str_contains($uri, ':')) {
      return $uri;
    }

    [$prefix, $local] = explode(':', $uri, 2);
    $prefix = strtolower(trim($prefix));
    $local = ltrim(trim($local), '/');
    if ($prefix === '' || $local === '') {
      return $uri;
    }

    $map = [];

    // Pull namespace map from table abstraction (keys are labels/prefixes).
    $table_map = (new Tables())->getNamespaces();
    if (is_array($table_map)) {
      foreach ($table_map as $k => $ns) {
        $key = strtolower(trim((string) $k));
        $base = trim((string) $ns);
        if ($key !== '' && $base !== '') {
          $map[$key] = $base;
        }
      }
    }

    // Fallback: read raw namespace list and index by explicit abbreviation/label.
    $api = \Drupal::service('rep.api_connector');
    $ns_list = $api->parseObjectResponse($api->namespaceList(), 'namespaceList');
    if (is_array($ns_list)) {
      foreach ($ns_list as $ns_obj) {
        if (!is_object($ns_obj)) {
          continue;
        }
        $base = trim((string) ($ns_obj->uri ?? ''));
        if ($base === '') {
          continue;
        }
        $keys = [
          strtolower(trim((string) ($ns_obj->abbreviation ?? ''))),
          strtolower(trim((string) ($ns_obj->label ?? ''))),
          strtolower(trim((string) ($ns_obj->prefix ?? ''))),
        ];
        foreach ($keys as $k) {
          if ($k !== '') {
            $map[$k] = $base;
          }
        }
      }
    }

    if (!isset($map[$prefix])) {
      return $uri;
    }

    $base = $map[$prefix];
    // If local is already absolute, keep it untouched.
    if (preg_match('/^https?:\/\//i', $local)) {
      return $local;
    }

    if (!str_ends_with($base, '/') && !str_ends_with($base, '#')) {
      $base .= '/';
    }

    return $base . $local;
  }

  /**
   * Cast database row values to response-safe scalar types.
   */
  private function normalizeRow(array $row): array {
    return [
      'id' => isset($row['id']) ? (int) $row['id'] : 0,
      'label' => (string) ($row['label'] ?? ''),
      'uberon_uri' => (string) ($row['uberon_uri'] ?? ''),
      'x_min' => isset($row['x_min']) ? (float) $row['x_min'] : 0,
      'x_max' => isset($row['x_max']) ? (float) $row['x_max'] : 0,
      'y_min' => isset($row['y_min']) ? (float) $row['y_min'] : 0,
      'y_max' => isset($row['y_max']) ? (float) $row['y_max'] : 0,
      'description' => (string) ($row['description'] ?? ''),
      'status' => isset($row['status']) ? (int) $row['status'] : 1,
      'weight' => isset($row['weight']) ? (int) $row['weight'] : 0,
      'changed' => isset($row['changed']) ? (int) $row['changed'] : 0,
    ];
  }

  /**
   * Returns whether anatomy mapping table exists.
   */
  private function mappingTableExists(): bool {
    return \Drupal::database()->schema()->tableExists(self::TABLE);
  }

}
