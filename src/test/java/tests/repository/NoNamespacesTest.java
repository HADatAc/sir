package tests.repository;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.*;
import tests.base.BaseRep;

import java.time.Duration;
import java.util.List;

import static org.junit.jupiter.api.Assertions.*;
import static tests.config.EnvConfig.FRONTEND_URL;
import static tests.config.EnvConfig.NAMESPACES_URL;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class NoNamespacesTest extends BaseRep {

    @Test
    public void verifyNamespaceTableIsEmpty() {
        driver.get(NAMESPACES_URL );

        wait.until(ExpectedConditions.presenceOfElementLocated(By.id("edit-element-table")));

        WebElement table = driver.findElement(By.id("edit-element-table"));
        List<WebElement> rows = table.findElements(By.cssSelector("tbody > tr"));

        // Verifica se não há nenhuma linha no corpo da tabela
        assertEquals(0, rows.size(), "Expected empty table but found " + rows.size() + " rows.");
        System.out.println("✅ Namespace table is empty as expected.");
    }

    @AfterAll
    void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
