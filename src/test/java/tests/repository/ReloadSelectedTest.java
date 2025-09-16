package tests.repository;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.support.ui.*;
import tests.base.BaseRep;

import java.util.List;

import static tests.config.EnvConfig.NAMESPACES_URL;

/*
    * Test to update each ontology individually and report the number of triples
    * AINDA NÃO IMPLEMENTADO O BOTÃO "Reload Selected Ontology" na API
    * UTILIZANDO O UPDATE POR ENQUANTO
 */
@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class ReloadSelectedTest extends BaseRep {

    @Test
    @Order(1)
    void testUpdateEachOntologyIndividuallyAndReport() throws InterruptedException {
        driver.get(NAMESPACES_URL);
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));

        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        int totalRows = rows.size();

        for (int i = 0; i < totalRows; i++) {
            // Recarrega a página e pega a nova lista de linhas
            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));
            rows = driver.findElements(By.cssSelector("table tbody tr"));

            // Marcar apenas a checkbox da linha atual
            WebElement row = rows.get(i);
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (!cells.isEmpty()) {
                WebElement checkbox = cells.get(0).findElement(By.cssSelector("input[type='checkbox']"));
                if (!checkbox.isSelected()) {
                    ((JavascriptExecutor) driver).executeScript("arguments[0].click();", checkbox);
                }
            }

            // Clicar no botão "Update Selected Ontology"
            WebElement updateBtn = wait.until(ExpectedConditions.elementToBeClickable(By.id("edit-reload-triples-selected-submit")));
            ((JavascriptExecutor) driver).executeScript("arguments[0].click();", updateBtn);

            // Esperar e atualizar até os triples mudarem
            int attempts = 0;
            int prevTriples = -1;
            while (attempts < 5) {
                Thread.sleep(6000);
                driver.navigate().refresh();
                wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));
                rows = driver.findElements(By.cssSelector("table tbody tr"));
                row = rows.get(i);
                cells = row.findElements(By.tagName("td"));
                if (cells.size() >= 7) {
                    String triplesText = cells.get(6).getText().trim();
                    int currentTriples = triplesText.isBlank() ? 0 : Integer.parseInt(triplesText.replaceAll("[^0-9]", ""));
                    if (currentTriples != prevTriples) {
                        prevTriples = currentTriples;
                        attempts = 0; // reinicia se mudou
                    } else {
                        attempts++;
                    }
                } else {
                    break; // linha incompleta, pular
                }
            }
        }

        // Mostrar resultado final
        driver.navigate().refresh();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));
        List<WebElement> finalRows = driver.findElements(By.cssSelector("table tbody tr"));
        System.out.println("=== Final Triples per Namespace ===");
        for (WebElement row : finalRows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 7) {
                String namespace = cells.get(2).getText().trim();
                String triples = cells.get(6).getText().trim();
                System.out.println("Namespace: " + namespace + " | Triples: " + triples);
            }
        }
    }

    @AfterAll
    void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
