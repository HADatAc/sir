package tests.DSG;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.WebDriverWait;
import org.openqa.selenium.support.ui.ExpectedConditions;

import java.time.Duration;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class DSGRegressionTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));

        driver.get("http://localhost:80/user/login");
        driver.findElement(By.id("edit-name")).sendKeys("admin");
        driver.findElement(By.id("edit-pass")).sendKeys("admin");
        driver.findElement(By.id("edit-submit")).click();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    private void goToDSGPage() {
        driver.get("http://localhost/std/list/study/_/_/1/12");
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table#edit-content")));
    }

    @Test
    @DisplayName("DSG: Table loads with rows and non-empty short name")
    void testTableLoadedAndShortNamePresent() {
        goToDSGPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table#edit-content tbody tr"));
        assertFalse(rows.isEmpty(), "Expected at least one DSG row");

        WebElement firstRow = rows.get(0);
        List<WebElement> cells = firstRow.findElements(By.tagName("td"));
        assertFalse(cells.get(1).getText().isBlank(), "Short Name should not be blank");
    }

    @Test
    @DisplayName("DSG: Log all DSG short names and URIs")
    void testLogDSGShortNamesAndURIs() {
        goToDSGPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table#edit-content tbody tr"));

        System.out.println("DSG entries found:");
        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            String uri = cells.get(0).getText().trim();
            String shortName = cells.get(1).getText().trim();
            System.out.printf("- URI: %s | Short Name: %s%n", uri, shortName);
        }

        assertTrue(rows.size() > 0, "Should log at least one DSG");
    }

    @Test
    @DisplayName("DSG: Check virtual columns count is numeric")
    void testVirtualColumnsValueIsNumeric() {
        goToDSGPage();
        WebElement cell = driver.findElement(By.cssSelector("table#edit-content tbody tr:first-child td:nth-child(5)")); // # Virt.Columns
        String value = cell.getText().trim();
        assertTrue(value.matches("\\d+"), "Virtual Columns should be a number");
    }

    @Test
    @DisplayName("DSG: Click View button and verify redirected to DSG detail page")
    void testNavigateToDSGViewPage() {
        goToDSGPage();

        WebElement viewButton = driver.findElement(By.cssSelector(
                "table#edit-content tbody tr:first-child td:last-child a[href*='describe_element']"
        ));
        assertNotNull(viewButton, "View button should be present");

        viewButton.click();

        // Aguarda até que a URL mude para algo que comece com /rep/uri/
        boolean redirected = wait.until(driver ->
                driver.getCurrentUrl().contains("/rep/uri/")
        );

        assertTrue(redirected, "Should be redirected to DSG detail page with /rep/uri/ in URL");
    }


    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
