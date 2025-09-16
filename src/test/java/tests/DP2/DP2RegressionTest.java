package tests.DP2;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.WebDriverWait;
import org.openqa.selenium.support.ui.ExpectedConditions;

import java.time.Duration;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class DP2RegressionTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));

        // Log in before all tests
        driver.get("http://localhost/user/login");
        driver.findElement(By.id("edit-name")).sendKeys("admin");
        driver.findElement(By.id("edit-pass")).sendKeys("admin");
        driver.findElement(By.id("edit-submit")).click();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    private void goToDP2Page() {
        // Go to the DP2 listing page
        driver.get("http://localhost/dpl/select/platform/1/9");
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("table#edit-element-table")));
    }

    @Test
    @DisplayName("DP2: Table loads and has rows with names")
    void testTableLoadedAndNamePresent() {
        goToDP2Page();
        List<WebElement> rows = driver.findElements(By.cssSelector("table#edit-element-table tbody tr"));
        assertFalse(rows.isEmpty(), "Expected at least one DP2 row");

        WebElement firstRow = rows.get(0);
        List<WebElement> cells = firstRow.findElements(By.tagName("td"));
        assertFalse(cells.get(2).getText().isBlank(), "Name column should not be blank");
    }

    @Test
    @DisplayName("DP2: Click URI and verify redirected page")
    void testNavigateToDP2ViewPage() {
        goToDP2Page();

        // Find the first URI link in the table
        WebElement uriLink = driver.findElement(By.cssSelector("table#edit-element-table tbody tr:first-child td:nth-child(2) a"));
        String originalWindow = driver.getWindowHandle();

        System.out.println("Clicking URI link: " + uriLink.getText());
        uriLink.click();

        // Wait for new window/tab to open
        wait.until(driver -> driver.getWindowHandles().size() > 1);
        System.out.println("New tab detected.");

        // Switch to the newly opened tab
        for (String windowHandle : driver.getWindowHandles()) {
            if (!windowHandle.equals(originalWindow)) {
                driver.switchTo().window(windowHandle);
                System.out.println("Switched to new tab with URL: " + driver.getCurrentUrl());
                break;
            }
        }

        // Wait for the new page to load with "/rep/uri/"
        wait.until(ExpectedConditions.urlContains("/rep/uri/"));
        String currentUrl = driver.getCurrentUrl();
        System.out.println("Landed on: " + currentUrl);

        assertTrue(currentUrl.contains("/rep/uri/"), "Should have navigated to a detailed URI view page");
    }

    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
