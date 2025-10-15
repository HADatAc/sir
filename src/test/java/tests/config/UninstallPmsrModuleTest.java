package tests.config;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.*;

import java.time.Duration;

import static tests.config.EnvConfig.*;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class UninstallPmsrModuleTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));

        // Login
        driver.get(LOGIN_URL);
        driver.findElement(By.id("edit-name")).sendKeys(USERNAME);
        driver.findElement(By.id("edit-pass")).sendKeys(PASSWORD);
        driver.findElement(By.id("edit-submit")).click();

        // Wait until user is logged in
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    @Test
    public void uninstallPmsrModule() {
        System.out.println("Navigating to uninstall modules page...");
        driver.get("http://localhost:80/admin/modules/uninstall");

        wait.until(ExpectedConditions.presenceOfElementLocated(By.id("edit-uninstall-pmsr")));

        System.out.println("Selecting pmsr module for uninstall...");
        WebElement checkbox = driver.findElement(By.id("edit-uninstall-pmsr"));
        if (!checkbox.isSelected()) {
            checkbox.click();
        }

        System.out.println("Clicking initial uninstall button...");
        WebElement uninstallButton = driver.findElement(By.id("edit-submit"));
        uninstallButton.click();

        System.out.println("Waiting for confirmation page...");
        wait.until(ExpectedConditions.urlContains("/admin/modules/uninstall/confirm"));

        System.out.println("Confirming uninstall on confirmation page...");
        WebElement confirmButton = wait.until(ExpectedConditions.elementToBeClickable(By.id("edit-submit")));
        confirmButton.click();

        System.out.println("Uninstall process completed.");
    }

    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
