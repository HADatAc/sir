package tests.base;

import java.io.File;
import java.time.Duration;
import java.util.concurrent.TimeUnit;

import org.junit.jupiter.api.AfterAll;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.junit.jupiter.api.Assertions.fail;
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
import static tests.config.EnvConfig.UPLOAD_URL;
import static tests.config.EnvConfig.USERNAME;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public abstract class BaseUpload {

    
    protected WebDriver driver;
    protected WebDriverWait wait;

    @BeforeAll
    void setup() {
        System.out.println("=== STARTING SETUP ===");
        logMemoryUsage("Before Chrome setup");
        
        try {
            System.setProperty("webdriver.chrome.driver", "/var/data/chromedriver/chromedriver");
            
            ChromeOptions options = new ChromeOptions();
            options.addArguments(
                "--headless=new",
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--disable-gpu",
                "--remote-allow-origins=*",
                "--disable-setuid-sandbox",
                "--disable-extensions",
                "--disable-background-networking",
                "--disable-sync",
                "--disable-translate",
                "--disable-background-timer-throttling",
                "--disable-renderer-backgrounding",
                "--disable-infobars",
                "--disable-popup-blocking",
                "--disable-notifications",
                "--disable-default-apps",
                "--disable-logging",
                "--disable-automation",
                "--window-size=1920,1080",
                "--memory-pressure-off",
                "--max_old_space_size=4096",
                "--disable-features=VizDisplayCompositor"
            );

            System.out.println("Creating ChromeDriver...");
            driver = new ChromeDriver(options);
            driver.manage().window().maximize();
            driver.manage().timeouts().pageLoadTimeout(30, TimeUnit.SECONDS);
            driver.manage().timeouts().implicitlyWait(5, TimeUnit.SECONDS);
            
            wait = new WebDriverWait(driver, Duration.ofSeconds(20));
            System.out.println("ChromeDriver created successfully");
            
            logMemoryUsage("After Chrome setup");

            System.out.println("Navigating to login page: " + LOGIN_URL);
            driver.get(LOGIN_URL);

            // Espera e preenche login
            System.out.println("Waiting for username field...");
            WebElement usernameField = wait.until(ExpectedConditions.visibilityOfElementLocated(By.id("edit-name")));
            usernameField.clear();
            usernameField.sendKeys(USERNAME);
            System.out.println("Username filled");

            WebElement passwordField = driver.findElement(By.id("edit-pass"));
            passwordField.clear();
            passwordField.sendKeys(PASSWORD);
            System.out.println("Password filled");

            // Clique robusto para login
            System.out.println("Submitting login...");
            clickElementRobust(By.id("edit-submit"));
            System.out.println("Login submitted.");

            // Aguarda toolbar do usuário aparecer
            System.out.println("Waiting for login confirmation...");
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
            System.out.println("Login successful.");
            
            logMemoryUsage("After login");
            System.out.println("=== SETUP COMPLETED ===");
            
        } catch (Exception e) {
            System.err.println("SETUP FAILED: " + e.getMessage());
            e.printStackTrace();
            if (driver != null) {
                try {
                    driver.quit();
                } catch (Exception ex) {
                    System.err.println("Failed to quit driver during cleanup: " + ex.getMessage());
                }
            }
            throw new RuntimeException("Setup failed", e);
        }
    }

    protected void navigateToUploadPage(String type) {
        String url = UPLOAD_URL + type + "/none/F";
        System.out.println("Navigating to upload page: " + url);
        driver.get(url);
        wait.until(ExpectedConditions.visibilityOfElementLocated(By.tagName("form")));
        System.out.println("Upload page loaded");
    }

    protected void fillInputByLabel(String label, String value) {
        System.out.println("Filling input with label: " + label);
        WebElement input = driver.findElement(By.xpath("//label[contains(text(),'" + label + "')]/following::input[1]"));
        input.clear();
        input.sendKeys(value);
        System.out.println("Input filled: " + label);
    }

    protected void uploadFile(File file) {
        assertTrue(file.exists(), "File does not exist at given path: " + file.getAbsolutePath());
        System.out.println("Starting file upload: " + file.getAbsolutePath());

        try {
            WebElement fileInput = driver.findElement(By.cssSelector("input[name='files[mt_filename]']"));
            ((JavascriptExecutor) driver).executeScript("arguments[0].scrollIntoView(true);", fileInput);
            ((JavascriptExecutor) driver).executeScript("arguments[0].style.display='block'; arguments[0].style.opacity=1;", fileInput);

            fileInput.sendKeys(file.getAbsolutePath());

            ((JavascriptExecutor) driver).executeScript(
                    "arguments[0].dispatchEvent(new Event('change', { bubbles: true }));", fileInput);

            System.out.println("File uploaded successfully: " + file.getAbsolutePath());

        } catch (Exception e) {
            System.err.println("File upload failed: " + e.getMessage());
            e.printStackTrace();
            fail("Failed to upload the file: " + e.getMessage());
        }
    }

    protected void submitFormAndVerifySuccess() {
        System.out.println("Submitting form...");
        try {
            By saveButtonLocator = By.xpath("//button[contains(text(), 'Save')]");
            clickElementRobust(saveButtonLocator);

            System.out.println("Waiting for confirmation message...");
            boolean confirmationAppeared = wait.until(driver ->
                    driver.findElements(By.cssSelector(".messages.status, .alert-success")).size() > 0 ||
                            driver.getPageSource().toLowerCase().contains("successfully")
            );

            assertTrue(confirmationAppeared, "No confirmation message found after upload.");
            System.out.println("Form submitted successfully");
        } catch (Exception e) {
            System.err.println("Form submission failed: " + e.getMessage());
            e.printStackTrace();
            fail("Failed to submit form: " + e.getMessage());
        }
    }

    protected void clickElementRobust(By locator) {
        int maxAttempts = 5;
        int attempt = 0;

        System.out.println("Robust click started for locator: " + locator);
        while (attempt < maxAttempts) {
            attempt++;
            try {
                WebElement element = wait.until(ExpectedConditions.elementToBeClickable(locator));
                clickElementRobust(element);
                System.out.println("Robust click completed at attempt " + attempt);
                return;
            } catch (StaleElementReferenceException sere) {
                System.out.println("Stale element, retry " + attempt);
                if (attempt < maxAttempts) {
                    try { Thread.sleep(1000); } catch (InterruptedException ignored) {}
                }
            } catch (Exception e) {
                System.out.println("Error at attempt " + attempt + ": " + e.getMessage());
                if (attempt == maxAttempts) {
                    throw new RuntimeException("Failed to click after " + maxAttempts + " attempts", e);
                }
                try { Thread.sleep(1000); } catch (InterruptedException ignored) {}
            }
        }
    }

    protected void clickElementRobust(WebElement element) {
        try {
            element.click();
            System.out.println("Standard click succeeded");
        } catch (Exception e) {
            System.out.println("Standard click failed, using JS click: " + e.getMessage());
            try {
                ((JavascriptExecutor) driver).executeScript("arguments[0].click();", element);
                System.out.println("JS click succeeded");
            } catch (Exception jsException) {
                System.err.println("JS click also failed: " + jsException.getMessage());
                throw jsException;
            }
        }

        try {
            Thread.sleep(500); // Increased pause to allow page processing
        } catch (InterruptedException ignored) {}
    }

    private void logMemoryUsage(String context) {
        try {
            Runtime runtime = Runtime.getRuntime();
            long totalMemory = runtime.totalMemory();
            long freeMemory = runtime.freeMemory();
            long usedMemory = totalMemory - freeMemory;
            long maxMemory = runtime.maxMemory();
            
            System.out.println("=== MEMORY USAGE (" + context + ") ===");
            System.out.println("Used: " + (usedMemory / 1024 / 1024) + " MB");
            System.out.println("Free: " + (freeMemory / 1024 / 1024) + " MB");
            System.out.println("Total: " + (totalMemory / 1024 / 1024) + " MB");
            System.out.println("Max: " + (maxMemory / 1024 / 1024) + " MB");
            System.out.println("=== END MEMORY USAGE ===");
        } catch (Exception e) {
            System.out.println("Could not log memory usage: " + e.getMessage());
        }
    }

    @AfterAll
    void teardown() {
        System.out.println("=== STARTING TEARDOWN ===");
        logMemoryUsage("Before teardown");
        
        if (driver != null) {
            try {
                System.out.println("Quitting WebDriver...");
                driver.quit();
                System.out.println("WebDriver quit successfully");
            } catch (Exception e) {
                System.err.println("Error during driver quit: " + e.getMessage());
            }
        }
        
        // Force garbage collection
        System.gc();
        
        logMemoryUsage("After teardown");
        System.out.println("=== TEARDOWN COMPLETED ===");
    }
}