package tests.base;

import java.time.Duration;

import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.TestInstance;
import org.openqa.selenium.By;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import static tests.config.EnvConfig.LOGIN_URL;
import static tests.config.EnvConfig.PASSWORD;
import static tests.config.EnvConfig.USERNAME;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public abstract class BaseTest {

    protected WebDriver driver;
    protected WebDriverWait wait;

    @BeforeEach
    public void setUp() throws InterruptedException {
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
        driver.findElement(By.id("edit-submit")).click();

        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    @AfterEach
    public void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
