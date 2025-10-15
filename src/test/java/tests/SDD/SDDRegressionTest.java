package tests.SDD;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import java.time.Duration;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class SDDRegressionTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));

        // Login before tests
        driver.get("http://localhost/user/login");
        driver.findElement(By.id("edit-name")).sendKeys("admin");
        driver.findElement(By.id("edit-pass")).sendKeys("admin");
        driver.findElement(By.id("edit-submit")).click();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    private void goToSDDPage() {
        // Navigate to the SDD listing page
        driver.get("http://localhost/sem/select/semanticdatadictionary/1/9");
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table#edit-element-table")));
    }

    @Test
    @DisplayName("SDD: Table loads with at least one entry with a name")
    void testTableLoadedAndNamePresent() {
        goToSDDPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table#edit-element-table tbody tr"));
        assertFalse(rows.isEmpty(), "Expected at least one SDD row");

        WebElement firstRow = rows.get(0);
        List<WebElement> cells = firstRow.findElements(By.tagName("td"));
        assertFalse(cells.get(2).getText().isBlank(), "Name column should not be blank");
    }

    @Test
    @DisplayName("SDD: Clicking URI opens detailed view (same tab navigation)")
    void testNavigateToSDDViewPage() {
        goToSDDPage();

        // Get current URL before clicking
        String currentUrlBefore = driver.getCurrentUrl();
        System.out.println("Current URL before click: " + currentUrlBefore);

        // Find and click the URI link in the first row
        WebElement uriLink = driver.findElement(By.cssSelector("table#edit-element-table tbody tr:first-child td:nth-child(2) a"));
        System.out.println("Clicking on SDD URI link: " + uriLink.getText());
        uriLink.click();

        // Wait until URL changes and contains /rep/uri/
        wait.until(ExpectedConditions.not(ExpectedConditions.urlToBe(currentUrlBefore)));
        wait.until(ExpectedConditions.urlContains("/rep/uri/"));

        String currentUrlAfter = driver.getCurrentUrl();
        System.out.println("Current URL after click: " + currentUrlAfter);

        assertTrue(currentUrlAfter.contains("/rep/uri/"), "Should be on a /rep/uri/ page after clicking the link.");
    }


    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
