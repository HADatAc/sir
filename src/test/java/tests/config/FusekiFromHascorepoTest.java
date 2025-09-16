package tests.config;

import org.junit.jupiter.api.*;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import tests.base.BaseRep;

import java.io.BufferedReader;
import java.io.DataOutputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

import static org.junit.jupiter.api.Assertions.*;
import static tests.config.EnvConfig.FUSEKI_URL;
import static tests.config.EnvConfig.YASGUI_ENDPOINT;

@TestMethodOrder(MethodOrderer.OrderAnnotation.class)
public class FusekiFromHascorepoTest extends BaseRep {

    private static WebDriver driver;

    @BeforeAll
    public static void setup() {
        ChromeOptions options = new ChromeOptions();
        //options.addArguments("--headless=new", "--no-sandbox", "--disable-dev-shm-usage");
        driver = new ChromeDriver(options);
    }

    @AfterAll
    public static void teardown() {
        if (driver != null) {
            driver.quit();
        }
    }

    @Test
    @Order(1)
    public void testDockerContainersAreRunning() throws Exception {
        assertTrue(isDockerContainerRunning("hascoapi_fuseki"), "Container 'hascoapi_fuseki' is not running.");
       // assertTrue(isDockerContainerRunning("hascoapi_fuseki_yasgui"), "Container 'hascoapi_fuseki_yasgui' is not running.");
    }

    private boolean isDockerContainerRunning(String containerName) throws Exception {
        ProcessBuilder builder = new ProcessBuilder("docker", "ps", "--format", "{{.Names}}");
        Process process = builder.start();
        BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()));
        String line;
        System.out.println("Checking running Docker containers for: " + containerName);
        while ((line = reader.readLine()) != null) {
            if (line.trim().equals(containerName)) {
                System.out.println("" + containerName + " is running.");
                return true;
            }
        }
        System.out.println(containerName + " is NOT running.");
        return false;
    }

    /*@Test
    @Order(2)
    public void testYasguiPageLoads() throws Exception {
        int status = getHttpStatusCode(YASGUI_ENDPOINT);
        assertEquals(200, status, "YASGUI web interface is not accessible.");
    }



    @Test
    @Order(3)
    public void testYasguiProxyToFuseki() throws Exception {
        driver.get(FUSEKI_URL);
        Thread.sleep(3000); // Allow UI to fully load
        System.out.println("fuseki page loaded successfully: " + driver.getTitle());
        String query = "SELECT * WHERE { ?s ?p ?o } LIMIT 1";
        int status = postSparqlQuery(YASGUI_ENDPOINT + "/query", query);
        System.out.println("POST to YASGUI returned status: " + status);
        assertEquals(200, status, "YASGUI (nginx) failed to proxy to Fuseki.");
    }

     */

    private int getHttpStatusCode(String urlString) throws Exception {
        URL url = new URL(urlString);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setConnectTimeout(3000);
        connection.setReadTimeout(3000);
        connection.setRequestMethod("GET");
        return connection.getResponseCode();
    }

    private int postSparqlQuery(String endpointUrl, String query) throws Exception {
        URL url = new URL(endpointUrl);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setConnectTimeout(5000);
        connection.setReadTimeout(5000);
        connection.setRequestMethod("POST");
        connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
        connection.setRequestProperty("Accept", "application/sparql-results+json");
        connection.setDoOutput(true);

        String urlParameters = "query=" + java.net.URLEncoder.encode(query, "UTF-8");

        try (DataOutputStream wr = new DataOutputStream(connection.getOutputStream())) {
            wr.writeBytes(urlParameters);
            wr.flush();
        }

        int responseCode = connection.getResponseCode();
        System.out.println("POST to " + endpointUrl + " returned status: " + responseCode);
        return responseCode;
    }
}
