package tests.config;

import java.time.Duration;

import org.junit.jupiter.api.AfterAll;
import org.junit.jupiter.api.Assertions;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.MethodOrderer;
import org.junit.jupiter.api.Order;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.TestMethodOrder;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.openqa.selenium.support.ui.WebDriverWait;

import static tests.config.EnvConfig.BACKEND_URL;

@TestMethodOrder(MethodOrderer.OrderAnnotation.class)
public class BEViaFEStatusTest {

    private static WebDriver driver;
    private static WebDriverWait wait;

    @BeforeAll
    public static void setup() {
        // Set Chrome to run in headless mode with proper configurations
        ChromeOptions options = new ChromeOptions();
        options.addArguments(
            "--headless=new",
            "--no-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu",
            "--remote-debugging-port=9222",
            "--window-size=1920,1080",
            "--ignore-certificate-errors"
        );



        driver = new ChromeDriver(options);
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));
    }

    @Test
    @Order(1)
    public void testBackendConnectivityViaJavaHTTP() throws Exception {
        var url = new java.net.URL(BACKEND_URL);
        var connection = (java.net.HttpURLConnection) url.openConnection();
        connection.setRequestMethod("HEAD");
        connection.setConnectTimeout(3000);
        connection.setReadTimeout(3000);
        int responseCode = connection.getResponseCode();

        if (responseCode == 200) {
            System.out.println("Backend is reachable and responded with HTTP 200 OK.");
        } else {
            System.out.println("Backend is not reachable, response code: " + responseCode);
        }

        Assertions.assertTrue(responseCode == 200, "Backend connected Successfully. Response code: " + responseCode);
        Assertions.assertTrue(responseCode >= 200 && responseCode < 500, "Backend is not reachable, Contact your administrator. Response code: " + responseCode);
    }




    @AfterAll
    public static void tearDown() {
        if (driver != null) driver.quit();
    }
}
