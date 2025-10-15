package tests.repository;

import org.junit.jupiter.api.*;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.*;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;
import org.openqa.selenium.*;
import org.openqa.selenium.NoSuchElementException;
import org.openqa.selenium.support.ui.*;
import tests.base.BaseRep;

import java.util.*;

import static tests.config.EnvConfig.FRONTEND_URL;
import static tests.config.EnvConfig.NAMESPACES_URL;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class ConfigValidationTest extends BaseRep {

    private final Map<String, Boolean> issues = new LinkedHashMap<>();

    @Test
    void testConfigurationAndNamespaces() throws InterruptedException {
        boolean configOk = validateRepositoryConfiguration();

        // If repository configuration is invalid, run RepositoryFormAutomationTest as a test
        if (!configOk) {
            System.out.println("⚠ Repository configuration invalid. Running RepositoryFormAutomationTest via JUnit launcher...");
            runJUnitTest(RepositoryFormAutomationTest.class);
            // After fixing, clear issues and re-validate
            issues.clear();
            validateRepositoryConfiguration();
        }

        validateAndReloadNamespacesIfNeeded();
    }

    /**
     * Runs another JUnit test class programmatically.
     */
    private void runJUnitTest(Class<?> testClass) {
        LauncherDiscoveryRequest request = LauncherDiscoveryRequestBuilder.request()
            .selectors(DiscoverySelectors.selectClass(testClass))
            .build();
        Launcher launcher = LauncherFactory.create();
        launcher.execute(request);
    }

    /**
     * Validates the repository configuration fields.
     * @return true if all required fields are filled, false otherwise.
     */
    private boolean validateRepositoryConfiguration() {
        driver.get(FRONTEND_URL + "/admin/config/rep");

        String[] requiredFields = {
            "Repository Short Name (ex. \"ChildFIRST\")",
            "Repository Full Name (ex. \"ChildFIRST: Focus on Innovation\")",
            "Repository URL (ex: http://childfirst.ucla.edu, http://tw.rpi.edu, etc.)",
            "Prefix for Base Namespace (ex: ufmg, ucla, rpi, etc.)",
            "URL for Base Namespace",
            "description for the repository that appears in the rep APIs GUI",
            "Sagres Base URL",
            "rep API Base URL"
        };

        boolean allFieldsFilled = true;

        for (String label : requiredFields) {
            WebElement input = findInputByLabel(label);
            if (input == null || input.getAttribute("value").trim().isEmpty()) {
                issues.put("Missing or empty field: " + label, false);
                allFieldsFilled = false;
            }
        }

        return allFieldsFilled;
    }

    /**
     * Validates namespaces and reloads triples if necessary.
     */
    private void validateAndReloadNamespacesIfNeeded() {
        driver.get(NAMESPACES_URL);
        wait.until(ExpectedConditions.presenceOfElementLocated(By.id("edit-element-table")));

        WebElement table = driver.findElement(By.id("edit-element-table"));
        List<WebElement> rows = table.findElements(By.cssSelector("tbody > tr"));

        boolean reloadNeeded = false;

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() < 8) continue;

            String abbrev = cells.get(1).getText().trim();
            String inMemory = cells.get(3).getText().trim().toLowerCase();
            String triples = cells.get(6).getText().trim();

            if (!"yes".equals(inMemory)) {
                issues.put("Namespace not in memory: " + abbrev, false);
            }

            if (triples.isEmpty() || triples.equals("0")) {
                issues.put("Triples missing for namespace: " + abbrev, false);
                reloadNeeded = true;
            }
        }

        if (reloadNeeded) {
            reloadTriples();
            validateTriplesAgain();
        }
    }

    /**
     * Clicks the button to reload triples and waits for the process to complete.
     */
    private void reloadTriples() {
        try {
            WebElement reloadButton = driver.findElement(By.xpath("//input[@value='Reload Triples from All Ontologies with URL']"));
            ((JavascriptExecutor) driver).executeScript("arguments[0].scrollIntoView(true);", reloadButton);
            Thread.sleep(1000);
            reloadButton.click();
            Thread.sleep(5000); // Wait for processing to complete
            driver.navigate().refresh();
            wait.until(ExpectedConditions.presenceOfElementLocated(By.id("edit-element-table")));
        } catch (Exception e) {
            issues.put("Failed to reload triples: " + e.getMessage(), false);
        }
    }

    /**
     * Checks triples again after reload to confirm that they are now present.
     */
    private void validateTriplesAgain() {
        WebElement table = driver.findElement(By.id("edit-element-table"));
        List<WebElement> rows = table.findElements(By.cssSelector("tbody > tr"));

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            if (cells.size() < 8) continue;

            String abbrev = cells.get(1).getText().trim();
            String triples = cells.get(6).getText().trim();

            if (triples.isEmpty() || triples.equals("0")) {
                issues.put("Triples still missing after reload: " + abbrev, false);
            }
        }
    }

    /**
     * Finds an input field associated with a label by matching label text.
     * @param labelText The text of the label.
     * @return The WebElement of the associated input, or null if not found.
     */
    private WebElement findInputByLabel(String labelText) {
        List<WebElement> labels = driver.findElements(By.tagName("label"));
        for (WebElement label : labels) {
            if (label.getText().trim().equals(labelText)) {
                String forAttr = label.getAttribute("for");
                if (forAttr != null && !forAttr.isEmpty()) {
                    try {
                        return driver.findElement(By.id(forAttr));
                    } catch (NoSuchElementException ignored) {}
                }
            }
        }
        return null;
    }

    /**
     * Shows a browser alert and console log if issues are found after a test.
     */
    @AfterEach
    void showPopupIfErrorsExist() {
        if (!issues.isEmpty()) {
            StringBuilder msg = new StringBuilder("Configuration issues found:\n");
            for (String issue : issues.keySet()) {
                msg.append("- ").append(issue).append("\n");
            }

            ((JavascriptExecutor) driver).executeScript("alert(arguments[0]);", msg.toString());
            System.out.println(msg.toString());
        } else {
            System.out.println("Configuration and namespaces are valid.");
        }
    }
}
