package tests.base;

import java.time.Duration;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.TestInstance;
import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.StaleElementReferenceException;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import static tests.config.EnvConfig.LOGIN_URL;
import static tests.config.EnvConfig.PASSWORD;
import static tests.config.EnvConfig.USERNAME;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public abstract class BaseRep {

    protected WebDriver driver;
    protected WebDriverWait wait;

    @BeforeAll
    void setup() {
        System.setProperty("webdriver.chrome.driver", "/var/data/chromedriver/chromedriver");
        ChromeOptions options = new ChromeOptions();
options.addArguments(
    "--headless=new",                     // Use new headless mode (Chrome 109+)
    "--no-sandbox",                       // Required for Docker/VM environments
    "--disable-dev-shm-usage",            // Avoid /dev/shm full issues
    "--disable-gpu",                      // Disable GPU (headless mode)
    "--remote-allow-origins=*",           // Required for Selenium 4.26+ with Chrome 125
    "--disable-setuid-sandbox",           // Security/sandbox reinforcement
    "--disable-extensions",               // Disable Chrome extensions
    "--disable-background-networking",    // Avoid background network calls
    "--disable-sync",                     // Disable sync
    "--disable-translate",                // Disable auto-translate
    "--disable-background-timer-throttling", // Prevent background throttling
    "--disable-renderer-backgrounding",      // Keep renderer active in background
    "--disable-infobars",                 // Remove "Chrome is being controlled" info bar
    "--disable-popup-blocking",           // Avoid popup blocking
    "--disable-notifications",            // Disable notifications
    "--disable-default-apps",             // Avoid default apps
    "--disable-logging",                  // Reduce unnecessary logs
    "--disable-automation",               // Remove automation banner
    "--window-size=1920,1080"             // Set window size for consistent element visibility
);
        driver = new ChromeDriver(options);
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(15));

        driver.get(LOGIN_URL);
        driver.findElement(By.id("edit-name")).sendKeys(USERNAME);
        driver.findElement(By.id("edit-pass")).sendKeys(PASSWORD);

        // Robust click for login
        clickElementRobust(By.id("edit-submit"));

        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    // ===== Robust Click Helpers =====

    protected void clickElementRobust(By locator) {
        int maxAttempts = 5;
        int attempt = 0;

        System.out.println("Robust click started for locator: " + locator);
        while (attempt < maxAttempts) {
            attempt++;
            try {
                WebElement element = wait.until(ExpectedConditions.elementToBeClickable(locator));
                clickElementRobust(element);
                System.out.println("Robust click finished at attempt " + attempt);
                return;
            } catch (StaleElementReferenceException sere) {
                System.out.println("Stale element, retry " + attempt);
            } catch (Exception e) {
                System.out.println("Error at attempt " + attempt + ": " + e.getMessage());
                if (attempt == maxAttempts) {
                    throw new RuntimeException("Failed to click after " + maxAttempts + " attempts", e);
                }
            }
        }
    }

    protected void clickElementRobust(WebElement element) {
        try {
            element.click();
            System.out.println("Standard click succeeded");
        } catch (Exception e) {
            System.out.println("Standard click failed, using JS click: " + e.getMessage());
            ((JavascriptExecutor) driver).executeScript("arguments[0].click();", element);
        }

        try {
            Thread.sleep(300); // Allow page processing
        } catch (InterruptedException ignored) {}
    }
}
