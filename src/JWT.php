<?php

namespace Drupal\sir;

class JWT {

  const JWT_SALT = "qwertyuiopasdfghjklzxcvbnm123456";

  static function generate_jwt($headers, $payload, $secret) {
    $headers_encoded = JWT::base64url_encode(json_encode($headers));
    $payload_encoded = JWT::base64url_encode(json_encode($payload));
    $signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
    $signature_encoded = JWT::base64url_encode($signature);
    $jwt = "$headers_encoded.$payload_encoded.$signature_encoded";
    return $jwt;
  }

  static function base64url_encode($str) {
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
  }

  static function show_user() {
    $user_id = \Drupal::currentUser()->id();
    $user_roles = \Drupal::currentUser()->getRoles();
    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
    $user_name = 'anonymous';
    if ($user_id != "0") {
      $user_name = $user_entity->name->value; 
    }
    $show = "";
    if ($user_entity != NULL) {
      $show .= "User ID: [" . $user_id . "]<br>";
      $show .= "User name: [" . $user_name . "]<br>";
      $show .= "User email: [" . $user_entity->mail->value . "]<br>";
      if ($user_roles != NULL) {
        $show .= "<ul>";
        foreach ($user_roles as $role) {
          $show .= "User role: [" . $role . "]<br>";        
        }
        $show .= "</ul>";
      }
    }
    return $show;
  }

  static function jwt() {
    $user_id = \Drupal::currentUser()->id();
    $user_roles = \Drupal::currentUser()->getRoles();
    $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
    $headers = array('alg'=>'HS256','typ'=>'JWT');
    $payload = array();
    if ($user_id == "0") {
      $payload = array(
        'sub'=>'0',
        'name'=>'anonymous', 
        'email'=>'anonymous@anonymous.org',
        'roles'=>$user_roles, 
        'exp'=>(time() + 600)
      );
    } else {
      $payload = array(
        'sub'=>$user_id,
        'name'=>$user_entity->name->value, 
        'email'=>$user_entity->mail->value,
        'roles'=>$user_roles, 
        'exp'=>(time() + 600)
      );
    }
    $jwt = JWT::generate_jwt($headers, $payload, JWT::JWT_SALT);
    return $jwt;
  }

}