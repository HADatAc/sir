package tests.STR;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import java.time.Duration;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class STRRegressionTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));

        // Login to the application before running tests
        driver.get("http://localhost/user/login");
        driver.findElement(By.id("edit-name")).sendKeys("admin");
        driver.findElement(By.id("edit-pass")).sendKeys("admin");
        driver.findElement(By.id("edit-submit")).click();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));

        System.out.println("Login successful.");
    }

    private void goToSTRPage() {
        // Navigate to the STR listing page
        driver.get("http://localhost/dpl/manage/deployments/active/1/9");
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table#edit-element-table")));
        System.out.println("Navigated to STR page and table is visible.");
    }

    @Test
    @DisplayName("STR: Table loads with at least one entry with a URI link")
    void testTableLoadedAndUriPresent() {
        goToSTRPage();
        List<WebElement> rows = driver.findElements(By.cssSelector("table#edit-element-table tbody tr"));
        assertFalse(rows.isEmpty(), "Expected at least one STR row");

        WebElement firstRow = rows.get(0);
        List<WebElement> cells = firstRow.findElements(By.tagName("td"));
        String uriText = cells.get(1).getText();
        assertFalse(uriText.isBlank(), "URI column should not be blank");
        System.out.println("First row URI: " + uriText);
    }

    @Test
    @DisplayName("STR: Clicking URI opens detailed view in new tab")
    void testNavigateToSTRViewPage() {
        goToSTRPage();

        // Find and click the URI link in the first row (2nd cell)
        WebElement uriLink = driver.findElement(By.cssSelector("table#edit-element-table tbody tr:first-child td:nth-child(2) a"));
        String originalWindow = driver.getWindowHandle();

        System.out.println("Clicking on STR URI link: " + uriLink.getText());
        uriLink.click();

        // Wait for a new tab or window to open
        wait.until(driver -> driver.getWindowHandles().size() > 1);
        System.out.println("New tab detected.");

        // Switch to the new tab
        for (String handle : driver.getWindowHandles()) {
            if (!handle.equals(originalWindow)) {
                driver.switchTo().window(handle);
                System.out.println("Switched to new tab with URL: " + driver.getCurrentUrl());
                break;
            }
        }

        // Wait for the page URL to contain "/rep/uri/"
        wait.until(ExpectedConditions.urlContains("/rep/uri/"));
        String currentUrl = driver.getCurrentUrl();
        System.out.println("Current URL after navigation: " + currentUrl);

        // Assert that the new page URL contains /rep/uri/
        assertTrue(currentUrl.contains("/rep/uri/"), "Expected URL to contain '/rep/uri/'");

        // Close the new tab and switch back to original
        driver.close();
        driver.switchTo().window(originalWindow);
        System.out.println("Closed new tab and returned to original window.");
    }

    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
            System.out.println("Browser closed.");
        }
    }
}
