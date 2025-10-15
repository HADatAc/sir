package tests.docker;

import org.junit.jupiter.api.Test;

import static org.junit.jupiter.api.Assertions.assertEquals;

import java.io.BufferedReader;
import java.io.File;
import java.io.InputStreamReader;

public class ResetDockerTest {

    private static final String WORKING_DIRECTORY = "../hascorepo";

    @Test
    public void testResetDockerEnvironment() throws Exception {
        System.out.println("Starting Docker environment reset...");

        assertEquals(0, runCommand(
            "BRANCH=DEV_V0.9.3 docker-compose -f docker-compose-hascogui-development.yml down -v",
            "Step 1 - GUI Down"
        ), "Failed to stop GUI container");

        assertEquals(0, runCommand(
            "BRANCH=DEV_V0.9.3 docker-compose -f docker-compose-hascoapi-development.yml down -v",
            "Step 2 - API Down"
        ), "Failed to stop API container");

        assertEquals(0, runCommand(
            "docker system prune -a -f",
            "Step 3 - Docker Prune"
        ), "Failed to prune Docker system");

        assertEquals(0, runCommand(
            "BRANCH=DEV_V0.9.3 docker-compose -f docker-compose-hascogui-development.yml up --build -d",
            "Step 4 - GUI Up"
        ), "Failed to start GUI container");

        assertEquals(0, runCommand(
            "BRANCH=DEV_V0.9.3 docker-compose -f docker-compose-hascoapi-development.yml up --build -d",
            "Step 5 - API Up"
        ), "Failed to start API container");

        System.out.println("Docker environment reset completed successfully.");
    }


    private int runCommand(String command, String stepName) throws Exception {
        ProcessBuilder builder;

        if (isWindows()) {
            if (command.contains("=")) {
                // Com variável de ambiente: set VAR=... && comando
                String[] parts = command.split(" ", 2);
                String var = parts[0].trim();     // BRANCH=DEV_V0.9.3
                String rest = parts[1].trim();    // docker-compose ...

                String setCmd = "set " + var + " && " + rest;
                builder = new ProcessBuilder("cmd.exe", "/c", setCmd);
            } else {
                // Comando simples (sem variável): execute direto
                builder = new ProcessBuilder("cmd.exe", "/c", command);
            }
        } else {
            builder = new ProcessBuilder("bash", "-c", command);
        }

        builder.directory(new File(WORKING_DIRECTORY));
        builder.redirectErrorStream(true);

        Process process = builder.start();

        try (BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()))) {
            String line;
            System.out.println("[" + stepName + "] Output:");
            while ((line = reader.readLine()) != null) {
                System.out.println("  " + line);
            }
        }

        int exitCode = process.waitFor();
        System.out.println("[" + stepName + "] Exit code: " + exitCode);
        return exitCode;
    }
    private boolean isWindows() {
        return System.getProperty("os.name").toLowerCase().contains("win");
    }

}
