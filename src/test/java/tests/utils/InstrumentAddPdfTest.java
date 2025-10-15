package tests.utils;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.*;

import java.io.File;
import java.time.Duration;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class InstrumentAddPdfTest {

    private WebDriver driver;
    private WebDriverWait wait;
    private JavascriptExecutor js;

    @BeforeAll
    void setup() {
        driver = new ChromeDriver();
        driver.manage().window().maximize();
        wait = new WebDriverWait(driver, Duration.ofSeconds(10));
        js = (JavascriptExecutor) driver;

        // Login
        driver.get("http://localhost/user/login");
        driver.findElement(By.id("edit-name")).sendKeys("admin");
        driver.findElement(By.id("edit-pass")).sendKeys("admin");
        driver.findElement(By.id("edit-submit")).click();
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    @Test
    @DisplayName("Attach PDF to DPQ Questionnaire")
    void attachPdfToDPQ() throws InterruptedException {
        // Acessar listagem de instrumentos
        driver.get("http://localhost/sir/select/instrument/2/9");

        // Selecionar checkbox do DPQ
        WebElement dpqCheckbox = wait.until(ExpectedConditions.presenceOfElementLocated(
                By.id("edit-element-table-httpshadatacorgontnhanesins-dpq-j-questionnaire-select")));

        js.executeScript("arguments[0].scrollIntoView(true);", dpqCheckbox);
        Thread.sleep(500); // Esperar rolagem
        if (!dpqCheckbox.isSelected()) {
            wait.until(ExpectedConditions.elementToBeClickable(dpqCheckbox)).click();
        }

        // Clicar no botão Edit Selected
        WebElement editButton = driver.findElement(By.id("edit-edit-selected-element"));
        js.executeScript("arguments[0].scrollIntoView(true);", editButton);
        Thread.sleep(500); // Esperar rolagem
        wait.until(ExpectedConditions.elementToBeClickable(editButton)).click();

        // Esperar redirecionamento
        wait.until(ExpectedConditions.urlContains("/sir/manage/editinstrument/"));

        // Selecionar "Upload" no dropdown de webdocument
        Select docTypeDropdown = new Select(wait.until(ExpectedConditions.elementToBeClickable(
                By.id("edit-instrument-webdocument-type"))));
        docTypeDropdown.selectByValue("upload");

// Disparar evento 'change' para ativar campo de upload
        JavascriptExecutor js = (JavascriptExecutor) driver;
        WebElement dropdown = driver.findElement(By.id("edit-instrument-webdocument-type"));
        js.executeScript("var event = new Event('change', { bubbles: true }); arguments[0].dispatchEvent(event);", dropdown);

// Esperar campo aparecer
        Thread.sleep(800);

// Fazer upload do arquivo PDF
        WebElement uploadInput = wait.until(ExpectedConditions.presenceOfElementLocated(
                By.id("edit-instrument-webdocument-upload-upload")));

        File file = new File("tests/testfiles/DPQ_Depression_Screen.pdf");
        if (!file.exists()) {
            throw new RuntimeException("Arquivo PDF não encontrado: " + file.getAbsolutePath());
        }

        uploadInput.sendKeys(file.getAbsolutePath());



        // Clicar em "Update"
        WebElement updateButton = driver.findElement(By.id("edit-update-submit"));
        js.executeScript("arguments[0].scrollIntoView(true);", updateButton);
        Thread.sleep(500); // Esperar rolagem
        wait.until(ExpectedConditions.elementToBeClickable(updateButton)).click();

        // Confirmar redirecionamento ou mensagem de sucesso
        wait.until(ExpectedConditions.or(
                ExpectedConditions.urlMatches(".*\\/sir\\/select\\/instrument\\/2\\/9"),
                ExpectedConditions.presenceOfElementLocated(By.className("messages--status"))
        ));
    }

    @AfterAll
    void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
