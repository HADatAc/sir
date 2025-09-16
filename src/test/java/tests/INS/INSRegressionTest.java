package tests.INS;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.WebDriverWait;
import org.openqa.selenium.support.ui.ExpectedConditions;

import java.time.Duration;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

/*
 This test suite logs into Drupal, goes to the instrument selection page,
 and verifies instrument listings with more precise checks:
 - table load
 - non-empty rows
 - first cell text presence
 - checking a specific column value
 - verifying navigation to instrument details
*/

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class INSRegressionTest { //extends BaseTest{

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

    private void goToInstrumentPage() {
        driver.get("http://localhost:80/sir/select/instrument/1/9");
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table")));
    }

    @Test
    @DisplayName("Basic table load and first-row content")
    void testTableLoadedAndFirstRow() {
        goToInstrumentPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        assertTrue(rows.size() > 0, "Expected at least one instrument row");
        WebElement firstCell = rows.get(0).findElements(By.tagName("td")).get(0);
        assertFalse(firstCell.getText().isBlank(), "First cell should contain text");
    }

    @Test
    @DisplayName("List all instrument names for logging")
    void testLogAllInstrumentNames() {
        goToInstrumentPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        System.out.println("Listed instruments:");
        for (WebElement row : rows) {
            String rowText = row.getText().trim();
            if (!rowText.isEmpty()) {
                System.out.println("- " + rowText);
            }
        }
        assertTrue(rows.size() > 0, "Should have printed at least one instrument row");
    }

    @Test
    @DisplayName("Check instrument contains expected column value")
    void testColumnSpecificValue() {
        goToInstrumentPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table tbody tr"));
        WebElement firstRow = rows.get(0);
        WebElement expectedColumn = firstRow.findElements(By.tagName("td")).get(1); // second column
        String colValue = expectedColumn.getText().trim();
        assertFalse(colValue.isEmpty(), "Column 2 should not be empty");
    }

    @Test
    @DisplayName("Click first instrument to see detail page")
    void testNavigateToInstrumentDetail() {
        goToInstrumentPage();

        WebElement firstLink = driver.findElement(By.cssSelector("table tbody tr:first-child td a"));
        String instrumentText = firstLink.getText().trim();
        firstLink.click();

        wait.until(ExpectedConditions.or(
                ExpectedConditions.visibilityOfElementLocated(By.cssSelector("h1.page-title")),
                ExpectedConditions.urlContains("/instrument/")
        ));

        assertTrue(driver.getPageSource().contains(instrumentText),
                "Instrument detail page should show the instrument name");
    }


    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
