package tests.config;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.TestInstance;

import tests.base.BaseRep;
import static tests.config.EnvConfig.FUSEKI_URL;

@TestInstance(TestInstance.Lifecycle.PER_CLASS)
public class FusekiConnectionTest extends BaseRep {

    private HttpClient client;

    @BeforeAll
    public void setup() {
        client = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(5))
            .build();
    }

    @Test
    public void testFusekiSparqlConnection() throws Exception {
        // Simple SPARQL query to check if Fuseki responds
        String sparqlQuery = "ASK { ?s ?p ?o }";

        HttpRequest request = HttpRequest.newBuilder()
            .uri(new URI(FUSEKI_URL + "/store/sparql"))
            .header("Content-Type", "application/sparql-query")
            .POST(HttpRequest.BodyPublishers.ofString(sparqlQuery))
            .build();

        HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());

        // Check that the endpoint is reachable and returns success
        assertEquals(200, response.statusCode(), "Fuseki SPARQL endpoint should return 200 OK");

        String body = response.body();
        System.out.println("Fuseki response body:\n" + body);

        // Verify that the response contains a valid SPARQL boolean result
        assertTrue(body.toLowerCase().contains("true") || body.toLowerCase().contains("false"),
            "Response should contain a boolean ASK result");
    }
}
