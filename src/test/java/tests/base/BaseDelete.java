package tests.base;

import java.time.Duration;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.fail;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.TestInstance;
import org.openqa.selenium.Alert;
import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.NoAlertPresentException;
import org.openqa.selenium.NoSuchElementException;
import org.openqa.selenium.StaleElementReferenceException;
import org.openqa.selenium.TimeoutException;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import static tests.config.EnvConfig.FILES_URL;
import static tests.config.EnvConfig.LOGIN_URL;
import static tests.config.EnvConfig.PASSWORD;
import static tests.config.EnvConfig.USERNAME;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public abstract class BaseDelete {
    protected WebDriver driver;
    protected WebDriverWait wait;
    protected final Map<String, Boolean> selectedRows = new HashMap<>();
    protected static final int MAX_ATTEMPTS = 10;
    protected static final int WAIT_INTERVAL_MS = 10000;

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
        clickElementRobust(By.id("edit-submit"));

        wait.until(ExpectedConditions.visibilityOfElementLocated(By.cssSelector("#toolbar-item-user")));
    }

    protected void deleteFile(String type, String fileName) throws InterruptedException {
        driver.get(FILES_URL + type + "/table/1/9/none");

        try {
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.tagName("table")));
        } catch (TimeoutException e) {
            fail("Table for type '" + type + "' not found.");
        }

        List<WebElement> rows = driver.findElements(By.xpath("//table//tbody//tr"));
        int selectedCount = 0;
        System.out.println("Total table rows found: " + rows.size());
        selectedRows.clear();

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 3) {
                String name = cells.get(2).getText().trim();

                if (name.equals(fileName)) {
                    try {
                        WebElement checkbox = cells.get(0).findElement(By.cssSelector("input[type='checkbox']"));
                        clickElementRobust(checkbox);
                        selectedRows.put(name, true);
                        selectedCount++;
                        System.out.println("Selected checkbox for file: " + name);
                        break;
                    } catch (Exception e) {
                        System.out.println("Failed to select checkbox: " + e.getMessage());
                        fail("Failed to select checkbox for file: " + fileName);
                    }
                }
            }
        }

        if (selectedCount == 0) {
            System.out.println("File '" + fileName + "' not found or could not be selected.");
            return;
        }

        try {
            String buttonId = "edit-delete-selected-element";
            WebElement deleteButton = driver.findElement(By.id(buttonId));
            clickElementRobust(deleteButton);

            wait.until(ExpectedConditions.alertIsPresent());
            Alert alert = driver.switchTo().alert();
            System.out.println("Delete confirmation alert: " + alert.getText());
            alert.accept();
        } catch (NoSuchElementException e) {
            fail("Delete button not found for type: " + type);
        } catch (NoAlertPresentException e) {
            fail("Expected confirmation alert not shown.");
        }

        int attempts = 0;
        boolean stillExists = true;

        while (attempts < MAX_ATTEMPTS && stillExists) {
            Thread.sleep(WAIT_INTERVAL_MS);
            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.tagName("table")));

            List<WebElement> updatedRows = driver.findElements(By.xpath("//table//tbody//tr"));
            stillExists = false;

            for (WebElement row : updatedRows) {
                List<WebElement> cells = row.findElements(By.tagName("td"));
                if (cells.size() >= 3) {
                    String name = cells.get(2).getText().trim();
                    if (name.equals(fileName)) {
                        stillExists = true;
                        break;
                    }
                }
            }

            System.out.println("Attempt " + (attempts + 1) + ": File still exists? " + stillExists);
            attempts++;
        }

        assertEquals(false, stillExists, "File '" + fileName + "' was not deleted.");
    }

    protected void deleteAllFiles(String type) throws InterruptedException {
        driver.get(FILES_URL + type + "/table/1/9/none");

        try {
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.tagName("table")));
        } catch (TimeoutException e) {
            fail("Table for type '" + type + "' not found.");
        }

        List<WebElement> rows = driver.findElements(By.xpath("//table//tbody//tr"));
        int selectedCount = 0;
        System.out.println("Total table rows found: " + rows.size());
        selectedRows.clear();

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 3) {
                String name = cells.get(2).getText().trim();

                try {
                    WebElement checkbox = cells.get(0).findElement(By.cssSelector("input[type='checkbox']"));
                    clickElementRobust(checkbox);
                    selectedRows.put(name, true);
                    selectedCount++;
                    System.out.println("Selected for deletion: " + name);
                } catch (Exception e) {
                    System.out.println("Failed to select checkbox for: " + name);
                }
            }
        }

        if (selectedCount == 0) {
            System.out.println("No files selected for deletion.");
            return;
        }

        try {
            String buttonId = "edit-delete-selected-element";
            WebElement deleteButton = driver.findElement(By.id(buttonId));
            clickElementRobust(deleteButton);

            wait.until(ExpectedConditions.alertIsPresent());
            Alert alert = driver.switchTo().alert();
            System.out.println("Delete confirmation alert: " + alert.getText());
            alert.accept();
        } catch (NoSuchElementException e) {
            fail("Delete button not found for type: " + type);
        } catch (NoAlertPresentException e) {
            fail("Expected confirmation alert not shown.");
        }

        int attempts = 0;
        boolean someStillExist = true;

        while (attempts < MAX_ATTEMPTS && someStillExist) {
            Thread.sleep(WAIT_INTERVAL_MS);
            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.tagName("table")));

            List<WebElement> updatedRows = driver.findElements(By.xpath("//table//tbody//tr"));
            someStillExist = false;

            for (WebElement row : updatedRows) {
                List<WebElement> cells = row.findElements(By.tagName("td"));
                if (cells.size() >= 3) {
                    String name = cells.get(2).getText().trim();
                    if (selectedRows.containsKey(name)) {
                        someStillExist = true;
                        break;
                    }
                }
            }

            System.out.println("Attempt " + (attempts + 1) + ": Some files still exist? " + someStillExist);
            attempts++;
        }

        assertEquals(false, someStillExist, "Some files were not deleted.");
    }

    public void quit() {
        if (driver != null) {
            driver.quit();
        }
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
