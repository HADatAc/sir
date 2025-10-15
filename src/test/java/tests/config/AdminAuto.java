package tests.config;

import java.time.Duration;

import org.junit.jupiter.api.AfterAll;
import static org.junit.jupiter.api.Assertions.assertTrue;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.TestInstance;
import org.openqa.selenium.By;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import tests.base.BaseTest;
import static tests.config.EnvConfig.FRONTEND_URL;
import static tests.config.EnvConfig.LOGIN_URL;
import static tests.config.EnvConfig.PASSWORD;
import static tests.config.EnvConfig.USERNAME;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class AdminAuto extends BaseTest {

    private WebDriver driver;
    private WebDriverWait wait;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));

        // Login step
        driver.get(LOGIN_URL);
        driver.findElement(By.id("edit-name")).sendKeys(USERNAME);
        driver.findElement(By.id("edit-pass")).sendKeys(PASSWORD);
        driver.findElement(By.id("edit-submit")).click();

        // Wait for login to complete by checking toolbar visibility
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    @Test
    @DisplayName("Verify Content editor and Administrator checkboxes are loaded and visible")
    void testCheckboxesLoaded() {
        // Navigate to user edit page
        driver.get(FRONTEND_URL + "/user/1/edit");

        // Wait for the checkboxes to be present
        WebElement contentEditorCheckbox = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.id("edit-roles-content-editor"))
        );
        WebElement administratorCheckbox = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.id("edit-roles-administrator"))
        );

        // Debug logs
        System.out.println("Content editor checkbox found: " + contentEditorCheckbox.isDisplayed());
        System.out.println("Administrator checkbox found: " + administratorCheckbox.isDisplayed());

        // Assertions to ensure they are present and visible
        assertTrue(contentEditorCheckbox.isDisplayed(), "Content editor checkbox should be visible.");
        assertTrue(administratorCheckbox.isDisplayed(), "Administrator checkbox should be visible.");
    }

    @Test
    @DisplayName("Ensure Content editor and Administrator checkboxes are checked and saved")
    void testEnsureCheckboxesCheckedAndSaved() {
        driver.get(FRONTEND_URL + "/user/1/edit");

        WebElement contentEditorCheckbox = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.id("edit-roles-content-editor"))
        );
        WebElement administratorCheckbox = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.id("edit-roles-administrator"))
        );

        if (!contentEditorCheckbox.isSelected()) {
            System.out.println("Content editor is unchecked. Clicking to check it.");
            contentEditorCheckbox.click();
        }

        if (!administratorCheckbox.isSelected()) {
            System.out.println("Administrator is unchecked. Clicking to check it.");
            administratorCheckbox.click();
        }

        WebElement saveButton = driver.findElement(By.id("edit-submit"));
        saveButton.click();

        // Espera mensagem de sucesso
        WebElement successMessage = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.cssSelector(".messages--status"))
        );

        System.out.println("Success message: " + successMessage.getText());
        assertTrue(successMessage.getText().toLowerCase().contains("saved"), "Expected success message to contain 'saved'.");

        // Recarrega a página e verifica se os checkboxes continuam marcados
        driver.get(FRONTEND_URL + "/user/1/edit");

        contentEditorCheckbox = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.id("edit-roles-content-editor"))
        );
        administratorCheckbox = wait.until(
                ExpectedConditions.visibilityOfElementLocated(By.id("edit-roles-administrator"))
        );

        assertTrue(contentEditorCheckbox.isSelected(), "Content editor checkbox must remain checked after save.");
        assertTrue(administratorCheckbox.isSelected(), "Administrator checkbox must remain checked after save.");
    }




    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
