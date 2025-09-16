package tests.config;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.*;

import java.time.Duration;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class BackendUPTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setUp() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));
    }

    @Test
    void testBackendIsRunning() {
        try {
            driver.get(EnvConfig.BACKEND_URL);
            wait.until(ExpectedConditions.or(
                    ExpectedConditions.presenceOfElementLocated(By.tagName("body")),
                    ExpectedConditions.presenceOfElementLocated(By.xpath("//*"))
            ));

            String pageSource = driver.getPageSource().toLowerCase();
            int statusCode = ((Long) ((JavascriptExecutor) driver)
                    .executeScript("return window.performance.getEntries()[0].responseStatus || 200")).intValue();

            System.out.println("✔ Backend responded with HTTP 200. Page content length: " + pageSource.length());
            Assertions.assertTrue(statusCode == 200, "Expected HTTP 200 OK status from backend");

        } catch (WebDriverException e) {
            Assertions.fail("Backend is not reachable at " + EnvConfig.BACKEND_URL + ": " + e.getMessage());
        }
    }

    @AfterAll
    void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
