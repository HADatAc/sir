package tests.repository;

import org.junit.jupiter.api.*;
import org.openqa.selenium.*;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.support.ui.*;
import tests.base.BaseRep;

import java.time.Duration;
import java.util.*;

import static org.junit.jupiter.api.Assertions.*;
import static tests.config.EnvConfig.FRONTEND_URL;
import static tests.config.EnvConfig.NAMESPACES_URL;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class NamespaceTableVerificationTest extends BaseRep {


    @Test
    public void verifyNamespaceTableContent() {
        driver.get(NAMESPACES_URL);
        wait.until(ExpectedConditions.presenceOfElementLocated(By.id("edit-element-table")));

        WebElement table = driver.findElement(By.id("edit-element-table"));
        List<WebElement> rows = table.findElements(By.cssSelector("tbody > tr"));

        // Map: Abbrev -> [Namespace, In-Memory, Source URL, MIME Type]
        Map<String, String[]> expectedRows = new LinkedHashMap<>();
        expectedRows.put("default", new String[]{"http://hadatac.org/kb/default/", "yes", "", ""});
        expectedRows.put("fhir", new String[]{"http://hl7.org/fhir/", "yes", "", ""});
        expectedRows.put("foaf", new String[]{"http://xmlns.com/foaf/0.1/", "yes", "http://xmlns.com/foaf/spec/index.rdf", "application/rdf+xml"});
        expectedRows.put("hadatac", new String[]{"https://hadatac.org/ont/hadatac#", "yes", "", ""});
        expectedRows.put("hasco", new String[]{"http://hadatac.org/ont/hasco/", "yes", "https://hadatac.org/ont/hasco/1.2", "text/turtle"});
        expectedRows.put("lcc-639-1", new String[]{"https://www.omg.org/spec/LCC/Languages/ISO639-1-LanguageCodes/", "yes", "https://www.omg.org/spec/LCC/20211101/Languages/ISO639-1-LanguageCodes.ttl", "text/turtle"});
        expectedRows.put("owl", new String[]{"http://www.w3.org/2002/07/owl#", "yes", "https://www.w3.org/2002/07/owl#", "text/turtle"});
        expectedRows.put("prov", new String[]{"http://www.w3.org/ns/prov#", "yes", "https://hadatac.org/ont/prov/", "text/turtle"});
        expectedRows.put("rdf", new String[]{"http://www.w3.org/1999/02/22-rdf-syntax-ns#", "yes", "https://www.w3.org/1999/02/22-rdf-syntax-ns#", "text/turtle"});
        expectedRows.put("rdfs", new String[]{"http://www.w3.org/2000/01/rdf-schema#", "yes", "https://www.w3.org/2000/01/rdf-schema#", "text/turtle"});
        expectedRows.put("schema", new String[]{"https://schema.org/", "yes", "https://raw.githubusercontent.com/schemaorg/schemaorg/main/data/releases/25.0/schemaorg-all-https.ttl", "text/turtle"});
        expectedRows.put("sio", new String[]{"http://semanticscience.org/resource/", "yes", "https://raw.githubusercontent.com/MaastrichtU-IDS/semanticscience/master/ontology/sio.owl", "application/rdf+xml"});
        expectedRows.put("skos", new String[]{"http://www.w3.org/2004/02/skos/core#", "yes", "", ""});
        expectedRows.put("test", new String[]{"http://hadatac.org/kb/test/", "yes", "", ""});
        expectedRows.put("vstoi", new String[]{"http://hadatac.org/ont/vstoi#", "yes", "https://hadatac.org/ont/vstoi/0.9", "text/turtle"});
        expectedRows.put("xsd", new String[]{"http://www.w3.org/2001/XMLSchema#", "yes", "http://www.w3.org/2001/XMLSchema#", "application/rdf+xml"});

        assertEquals(expectedRows.size(), rows.size(), "Unexpected number of namespace rows.");

        for (WebElement row : rows) {
            List<WebElement> cells = row.findElements(By.tagName("td"));
            assertTrue(cells.size() >= 8, "Row doesn't have at least 8 columns");

            String abbrev = cells.get(1).getText().trim();
            assertTrue(expectedRows.containsKey(abbrev), "Unexpected Abbrev: " + abbrev);

            String[] expected = expectedRows.get(abbrev);
            assertEquals(expected[0], cells.get(2).getText().trim(), "Namespace mismatch for: " + abbrev);
            assertEquals(expected[1], cells.get(3).getText().trim(), "In-Memory mismatch for: " + abbrev);
            assertEquals(expected[2], cells.get(4).getText().trim(), "Source URL mismatch for: " + abbrev);
            assertEquals(expected[3], cells.get(5).getText().trim(), "MIME Type mismatch for: " + abbrev);
        }
        System.out.println("Namespace table content verified successfully.");
    }
    @Test
    void testAllTriplesColumnAreEmpty() {
        driver.get(NAMESPACES_URL);
        wait.until(ExpectedConditions.presenceOfElementLocated(By.id("edit-element-table")));
        // Acesse a tabela de namespaces (ajuste o seletor conforme o seu HTML)
        WebElement table = driver.findElement(By.id("edit-element-table")); // substitua pelo id correto

        // Pega todas as linhas, ignorando o cabeçalho (thead)
        List<WebElement> rows = table.findElements(By.cssSelector("tbody tr"));

        // ndice da coluna "Triples" (exemplo: 6)
        int triplesColumnIndex = 6;

        for (WebElement row : rows) {
            List<WebElement> cols = row.findElements(By.tagName("td"));
            String triplesText = cols.get(triplesColumnIndex).getText().trim();
            Assertions.assertTrue(triplesText.isEmpty(), "Expected empty Triples column but found: " + triplesText);
        }
        System.out.println("All Triples columns are empty as expected.");
    }


    @AfterAll
    void tearDown() {
        if (driver != null) {
            driver.quit();
        }
    }
}
