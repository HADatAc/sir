package tests.base;

import java.time.Duration;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import org.junit.jupiter.api.AfterAll;
import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.fail;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.TestInstance;
import org.openqa.selenium.Alert;
import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
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
public abstract class BaseIngest {

    protected WebDriver driver;
    protected WebDriverWait wait;
    public static String ingestMode = "current"; // default
    String buttonName = "ingest_mt_" + ingestMode;
    protected final Map<String, Boolean> selectedRows = new HashMap<>();
    protected static final int MAX_ATTEMPTS = 60;
    protected static final int WAIT_INTERVAL_MS = 5000;

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

    protected void ingestFile(String type) throws InterruptedException {
        driver.get(FILES_URL + type + "/table/1/9/none");
        Thread.sleep(3000); // Wait for UI to update
        System.out.println("Ingesting files of type: " + type);
        try {
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.tagName("table")));
        } catch (TimeoutException e) {
            fail("Table for type '" + type + "' not found.");
        }
        Thread.sleep(2000);
        List<WebElement> rows = driver.findElements(By.xpath("//table//tbody//tr"));
        int selectedCount = 0;
        System.out.println("Total table rows found: " + rows.size());
        Thread.sleep(1000);
        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 5) {
                String status = cells.get(4).getText().trim();
                String rowKey = cells.get(1).getText().trim(); // unique name or ID

                if ("UNPROCESSED".equalsIgnoreCase(status)) {
                    try {
                        WebElement checkbox = cells.get(0).findElement(By.cssSelector("input[type='checkbox']"));
                        clickElementRobust(checkbox);
                        selectedRows.put(rowKey, true);
                        selectedCount++;
                        System.out.println("Selected row: " + rowKey);
                    } catch (Exception e) {
                        System.out.println("Failed to select checkbox: " + e.getMessage());
                    }
                }
            }
        }

        if (selectedCount == 0) {
            System.out.println("No UNPROCESSED entries found for type: " + type);
            return;
        }

        System.out.println("Total selected entries: " + selectedCount);

        Thread.sleep(3000); // Wait for UI to update

        try {
            WebElement ingestButton = wait.until(ExpectedConditions.elementToBeClickable(By.name(buttonName)));
            clickElementRobust(ingestButton);

            try {
                wait.until(ExpectedConditions.alertIsPresent());
                Alert alert = driver.switchTo().alert();
                System.out.println("Ingest confirmation: " + alert.getText());
                alert.accept();
            } catch (TimeoutException e) {
                System.out.println("No confirm dialog appeared.");
            }
        } catch (TimeoutException | NoSuchElementException e) {
            fail("Ingest button with name '" + buttonName + "' not found or not clickable.");
        }

        Thread.sleep(2000);
        // Retry check loop
        int attempts = 0;
        int processedCount = 0;

        while (attempts < MAX_ATTEMPTS) {
            Thread.sleep(WAIT_INTERVAL_MS);
            driver.navigate().refresh();
            Thread.sleep(3000); // Wait for UI to update

            List<WebElement> updatedRows = driver.findElements(By.xpath("//table//tbody//tr"));
            processedCount = 0;

            for (WebElement row : updatedRows) {
                List<WebElement> cells = row.findElements(By.tagName("td"));
                if (cells.size() >= 5) {
                    String rowKey = cells.get(1).getText().trim();
                    String newStatus = cells.get(4).getText().trim();

                    if (selectedRows.containsKey(rowKey) && "PROCESSED".equalsIgnoreCase(newStatus)) {
                        processedCount++;
                    }
                }
            }

            System.out.println("Attempt " + (attempts + 1) + ": Processed " + processedCount + " of " + selectedCount);

            if (processedCount == selectedCount) {
                break;
            }

            attempts++;
        }

        assertEquals(selectedCount, processedCount,
                "Not all selected entries were processed.");
    }

    protected void ingestSpecificINS(String fileName) throws InterruptedException {
        String type = "ins";
        driver.get(FILES_URL + type + "/table/1/9/none");

        wait.until(ExpectedConditions.visibilityOfElementLocated(By.id("edit-element-table")));

        List<WebElement> rows = driver.findElements(By.xpath("//table[@id='edit-element-table']//tbody//tr"));
        int selectedCount = 0;
        selectedRows.clear();

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 5) {
                String name = cells.get(2).getText().trim(); // column 2 is "Name"
                String status = cells.get(4).getText().replaceAll("\\<.*?\\>", "").trim(); // remove <b><font>

                if (name.equalsIgnoreCase(fileName) && status.equalsIgnoreCase("UNPROCESSED")) {
                    try {
                        WebElement checkbox = row.findElement(By.cssSelector("input[type='checkbox']"));
                        clickElementRobust(checkbox);
                        selectedRows.put(name, true);
                        selectedCount++;
                        System.out.println("Selected file: " + name);
                        break; // only one
                    } catch (Exception e) {
                        fail("Could not click checkbox for file: " + name + ". Error: " + e.getMessage());
                    }
                }
            }
        }

        if (selectedCount == 0) {
            fail("File '" + fileName + "' not found with UNPROCESSED status.");
        }

        try {
            WebElement ingestButton = wait.until(ExpectedConditions.elementToBeClickable(By.name(buttonName)));
            clickElementRobust(ingestButton);

            try {
                wait.until(ExpectedConditions.alertIsPresent());
                Alert alert = driver.switchTo().alert();
                System.out.println("Ingest confirmation: " + alert.getText());
                alert.accept();
            } catch (TimeoutException e) {
                System.out.println("No confirm dialog appeared.");
            }
        } catch (TimeoutException | NoSuchElementException e) {
            fail("Ingest button with name '" + buttonName + "' not found or not clickable.");
        }

        // Wait and verify processing
        int attempts = 0;
        while (attempts < MAX_ATTEMPTS) {
            Thread.sleep(WAIT_INTERVAL_MS);
            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.id("edit-element-table")));

            boolean processed = false;
            List<WebElement> updatedRows = driver.findElements(By.xpath("//table[@id='edit-element-table']//tbody//tr"));
            for (WebElement row : updatedRows) {
                List<WebElement> cells = row.findElements(By.tagName("td"));
                if (cells.size() >= 5) {
                    String name = cells.get(2).getText().trim();
                    String newStatus = cells.get(4).getText().replaceAll("\\<.*?\\>", "").trim();

                    if (name.equalsIgnoreCase(fileName) && newStatus.equalsIgnoreCase("PROCESSED")) {
                        processed = true;
                        break;
                    }
                }
            }

            if (processed) {
                System.out.println("File '" + fileName + "' was successfully processed.");
                return;
            }

            attempts++;
            System.out.println("Attempt " + attempts + " - still waiting...");
        }

        fail("File '" + fileName + "' was not processed after " + MAX_ATTEMPTS + " attempts.");
    }

    protected void ingestSpecificSDD(String fileName) throws InterruptedException {
        String type = "sdd";
        driver.get(FILES_URL + type + "/table/1/9/none");

        wait.until(ExpectedConditions.visibilityOfElementLocated(By.id("edit-element-table")));

        List<WebElement> rows = driver.findElements(By.xpath("//table[@id='edit-element-table']//tbody//tr"));
        int selectedCount = 0;
        selectedRows.clear();

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() >= 5) {
                String name = cells.get(2).getText().trim(); // column 2 is "Name"
                String status = cells.get(4).getText().replaceAll("\\<.*?\\>", "").trim();

                if (name.equalsIgnoreCase(fileName) && status.equalsIgnoreCase("UNPROCESSED")) {
                    try {
                        WebElement checkbox = row.findElement(By.cssSelector("input[type='checkbox']"));
                        clickElementRobust(checkbox);
                        selectedRows.put(name, true);
                        selectedCount++;
                        System.out.println("Selected file: " + name);
                        break; // only one
                    } catch (Exception e) {
                        fail("Could not click checkbox for file: " + name + ". Error: " + e.getMessage());
                    }
                }
            }
        }

        if (selectedCount == 0) {
            fail("File '" + fileName + "' not found with UNPROCESSED status.");
        }

        try {
            WebElement ingestButton = wait.until(ExpectedConditions.elementToBeClickable(By.name(buttonName)));
            clickElementRobust(ingestButton);

            try {
                wait.until(ExpectedConditions.alertIsPresent());
                Alert alert = driver.switchTo().alert();
                System.out.println("Ingest confirmation: " + alert.getText());
                alert.accept();
            } catch (TimeoutException e) {
                System.out.println("No confirm dialog appeared.");
            }
        } catch (TimeoutException | NoSuchElementException e) {
            fail("Ingest button with name '" + buttonName + "' not found or not clickable.");
        }

        // Wait and verify processing
        int attempts = 0;
        while (attempts < MAX_ATTEMPTS) {
            Thread.sleep(WAIT_INTERVAL_MS);
            driver.navigate().refresh();
            wait.until(ExpectedConditions.visibilityOfElementLocated(By.id("edit-element-table")));

            boolean processed = false;
            List<WebElement> updatedRows = driver.findElements(By.xpath("//table[@id='edit-element-table']//tbody//tr"));
            for (WebElement row : updatedRows) {
                List<WebElement> cells = row.findElements(By.tagName("td"));
                if (cells.size() >= 5) {
                    String name = cells.get(2).getText().trim();
                    String newStatus = cells.get(4).getText().replaceAll("\\<.*?\\>", "").trim();

                    if (name.equalsIgnoreCase(fileName) && newStatus.equalsIgnoreCase("PROCESSED")) {
                        processed = true;
                        break;
                    }
                }
            }

            if (processed) {
                System.out.println("File '" + fileName + "' was successfully processed.");
                return;
            }

            attempts++;
            System.out.println("Attempt " + attempts + " - still waiting...");
        }

        fail("File '" + fileName + "' was not processed after " + MAX_ATTEMPTS + " attempts.");
    }

    public String getIngestMode() {
        return ingestMode;
    }

    public void setIngestMode(String ingestMode) {
        this.ingestMode = ingestMode;
    }

    @AfterAll
    void teardown() {
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
