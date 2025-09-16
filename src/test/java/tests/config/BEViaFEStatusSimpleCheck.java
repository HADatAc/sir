package tests.config;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.junit.jupiter.api.Assertions.fail;
import org.junit.jupiter.api.Test;

import static tests.config.EnvConfig.BACKEND_URL;

public class BEViaFEStatusSimpleCheck {

    @Test
    public void testBackendApiIsReachable() {
        String targetUrl = BACKEND_URL;

        try {
            boolean reachable = isApiReachable(targetUrl);

            if (!reachable) {
                System.out.println("API is not reachable at " + targetUrl);

                // Wait 2 seconds before docker check
                Thread.sleep(2000);

                // Check if Docker container for the API is running
                boolean dockerUp;
                try {
                    dockerUp = isDockerApiContainerRunning();
                } catch (Exception e) {
                    System.out.println("Docker is not running or not accessible: " + e.getMessage());
                    fail("Docker is not running or not accessible");
                    return;
                }

                if (!dockerUp) {
                    System.out.println("Docker container for API not running, attempting to start it...");

                    // Wait 2 seconds before running docker-compose up
                    Thread.sleep(2000);

                    int exitCode = startAllContainers();

                    assertEquals(0, exitCode, "Failed to start Docker containers");

                    // Wait longer for the containers and API to fully start
                    System.out.println("Waiting 15 seconds for containers to initialize...");
                    Thread.sleep(15000);

                    reachable = isApiReachable(targetUrl);
                    assertTrue(reachable, "API is not reachable even after starting Docker containers");
                } else {
                    System.out.println("Docker container is running but API is still unreachable.");
                    fail("API is unreachable even though Docker container is running");
                }
            } else {
                System.out.println("API is reachable at " + targetUrl);
                assertTrue(true);
            }
        } catch (Exception e) {
            fail("Test failed due to exception: " + e.getMessage());
        }
    }

    private boolean isApiReachable(String targetUrl) {
        try {
            HttpURLConnection connection = (HttpURLConnection) new URL(targetUrl).openConnection();
            connection.setRequestMethod("GET");
            connection.setConnectTimeout(3000); // 3-second timeout
            connection.connect();

            int code = connection.getResponseCode();
            return (code >= 200 && code < 400);
        } catch (IOException e) {
            return false;
        }
    }

    private boolean isDockerApiContainerRunning() throws IOException, InterruptedException {
        String filter = "hascoapi"; // adjust container name accordingly
        ProcessBuilder pb = createProcessBuilder("docker ps --filter name=" + filter + " --format \"{{.Names}}\"");
        Process process = pb.start();

        BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()));
        String line = reader.readLine();
        int exitCode = process.waitFor();

        return exitCode == 0 && line != null && !line.isEmpty();
    }

    private int startAllContainers() throws IOException, InterruptedException {
        System.out.println("Starting all Docker containers...");

        String cmdApi = "docker-compose -f docker-compose-hascoapi-development.yml up --build -d";
        String cmdGui = "docker-compose -f docker-compose-hascogui-development.yml up --build -d";

        String command;

        if (isWindows()) {
            // Windows cmd.exe syntax to set env var for a single command
            command = "set BRANCH=DEV_V0.9.3 && " + cmdApi + " && " + "set BRANCH=DEV_V0.9.3 && " + cmdGui;
        } else {
            // Linux / bash syntax
            command = "BRANCH=DEV_V0.9.3 " + cmdApi + " && BRANCH=DEV_V0.9.3 " + cmdGui;
        }

        ProcessBuilder pb = createProcessBuilder(command);
        pb.redirectErrorStream(true);
        Process process = pb.start();

        try (BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()))) {
            String line;
            while ((line = reader.readLine()) != null) {
                System.out.println(line);
            }
        }

        int exitCode = process.waitFor();
        System.out.println("[Start API Container] Exit code: " + exitCode);

        if (exitCode == 0) {
            System.out.println("All Docker containers started successfully.");
        } else {
            System.out.println("Failed to start Docker containers.");
        }

        return exitCode;
    }

    private ProcessBuilder createProcessBuilder(String command) {
        if (isWindows()) {
            return new ProcessBuilder("cmd.exe", "/c", command);
        } else {
            return new ProcessBuilder("bash", "-c", command);
        }
    }

    private boolean isWindows() {
        return System.getProperty("os.name").toLowerCase().contains("win");
    }
}
