package tests.repository;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.support.ui.*;
import tests.base.BaseRep;

import java.util.*;
import java.util.stream.Collectors;

import static tests.config.EnvConfig.NAMESPACES_URL;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
@TestMethodOrder(MethodOrderer.OrderAnnotation.class)
public class NamespacesNHANESTest extends BaseRep {

    private final List<String> expectedPrefixes = List.of(
        "nhanes",
        "hasco",
        "sio",
        "obo",
        "exo",
        "hhear",
        "uberon",
        "chebi",
        "cmo",
        "snomedct",
        "pato"
    );

    @Test
    @Order(1)
    void testNamespacesPresence() {
        driver.get(NAMESPACES_URL);
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));

        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        Set<String> foundPrefixes = rows.stream()
            .map(row -> row.findElements(By.tagName("td")))
            .filter(cells -> cells.size() >= 1)
            .map(cells -> cells.get(1).getText().trim())  // coluna de prefixo
            .collect(Collectors.toSet());

        List<String> missing = expectedPrefixes.stream()
            .filter(prefix -> !foundPrefixes.contains(prefix))
            .collect(Collectors.toList());

        if (!missing.isEmpty()) {
            System.out.println("Missing prefixes: " + String.join(", ", missing));
            Assertions.fail("Some expected prefixes are missing from the table.");
        } else {
            System.out.println("All expected NHANES prefixes are present.");
        }
    }

    @Test
    @Order(2)
    void testReloadTriplesAndReport() {
        driver.get(NAMESPACES_URL);
        WebElement reloadBtn = wait.until(ExpectedConditions.elementToBeClickable(By.id("edit-reload-triples-submit")));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", reloadBtn);

        int previousCount = -1;
        int stableCount = 0;
        int maxStableTries = 5; // número de tentativas consecutivas sem mudança
        int waitBetweenChecksMs = 2000;

        while (stableCount < maxStableTries) {
            try {
                Thread.sleep(waitBetweenChecksMs);
            } catch (InterruptedException ignored) {}

            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));

            List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
            long currentCount = rows.stream()
                .map(row -> row.findElements(By.tagName("td")))
                .filter(cells -> cells.size() >= 7)
                .map(cells -> cells.get(6).getText().trim())
                .filter(triples -> !triples.isBlank() && triples.matches("\\d+"))
                .count();

            if (currentCount > previousCount) {
                previousCount = (int) currentCount;
                stableCount = 0; // reinicia contagem estável
            } else {
                stableCount++;
            }
        }

        System.out.println("=== Triples count and In-Memory status ===");

        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 7) {
                String namespace = cells.get(2).getText().trim();
                String inMemory = cells.get(3).getText().trim();
                String triples = cells.get(6).getText().trim();
                System.out.println("Namespace: " + namespace);
                System.out.println("In-Memory: " + inMemory);
                System.out.println("Triples: " + triples);
                System.out.println("---");
            }
        }
    }



    @Test
    @Order(3)
    void testDeleteTriplesAndVerifyEmpty() throws InterruptedException {
        driver.get(NAMESPACES_URL);
        WebElement deleteBtn = wait.until(ExpectedConditions.elementToBeClickable(By.id("edit-delete-triples-submit")));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", deleteBtn);

        Thread.sleep(10000);
        driver.navigate().refresh();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));

        System.out.println("=== Checking for empty triples ===");

        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 7) {
                String namespace = cells.get(2).getText().trim();
                String triples = cells.get(6).getText().trim();
                System.out.println("Namespace: " + namespace + " | Triples: '" + triples + "'");
                Assertions.assertTrue(triples.isBlank(), "Triples should be empty for: " + namespace);
            }
        }

        System.out.println("All triples have been successfully deleted.");
    }

    @AfterAll
    void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
