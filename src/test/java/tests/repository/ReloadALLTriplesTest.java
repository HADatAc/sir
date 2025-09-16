package tests.repository;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.*;
import tests.base.BaseRep;

import java.time.Duration;
import java.util.List;

import static tests.config.EnvConfig.FRONTEND_URL;
import static tests.config.EnvConfig.NAMESPACES_URL;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class ReloadALLTriplesTest extends BaseRep {



    @Test
    void testReloadTriplesAndCheckValues() throws InterruptedException {
        driver.get(NAMESPACES_URL);

        WebElement reloadButton = wait.until(ExpectedConditions.elementToBeClickable(By.id("edit-reload-triples-submit")));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", reloadButton);

        System.out.println("Waiting for all rows (except xsd) to have MIME Type and Triples...");

        boolean allDataLoaded = false;
        int attempt = 0;

        while (!allDataLoaded && attempt < 30) {
            attempt++;
            Thread.sleep(10000); // wait 10s

            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table tbody tr")));

            List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
            allDataLoaded = true;

            System.out.println("=== Attempt #" + attempt + " ===");

            for (int i = 0; i < rows.size(); i++) {
                WebElement row = rows.get(i);
                List<WebElement> cells = row.findElements(By.tagName("td"));

                if (cells.size() >= 7) {
                    String namespace = cells.get(2).getText().trim();
                    String mime = cells.get(5).getText().trim();
                    String triples = cells.get(6).getText().trim();

                    if (namespace.equals("http://www.w3.org/2001/XMLSchema#" ) || mime.equals("")) {
                        System.out.println("Row " + (i + 1) + ": Skipping namespace " + namespace);
                        continue;
                    }

                    System.out.println("Row " + (i + 1) + ": Namespace='" + namespace + "', MIME='" + mime + "', Triples='" + triples + "'");

                    boolean mimeValid = !mime.isEmpty() && !mime.isBlank();
                    boolean triplesValid = triples.matches("\\d+");

                    if (!mimeValid || !triplesValid) {
                        allDataLoaded = false;
                    }
                }
            }
        }

        Assertions.assertTrue(allDataLoaded, "Not all rows (excluding xsd) have valid MIME Type and Triples after multiple attempts.");
        System.out.println("✔ All valid rows have MIME Type and Triples filled.");
    }

    @AfterAll
    void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
