package tests.docker;

import java.io.BufferedReader;
import java.io.File;
import java.io.InputStreamReader;

public class DockerAllUpTest {

    private static final String WORKING_DIRECTORY = "../hascorepo";

    /**
     * Starts all docker containers used by the system.
     * @throws Exception if any docker command fails
     */
    public static void startAllContainers() throws Exception {
        System.out.println("Starting all Docker containers...");

        int exitCode;

        exitCode = runCommand(
            "BRANCH=DEV_V0.9.3 docker-compose -f docker-compose-hascogui-development.yml up --build -d",
            "Start GUI Container"
        );
        if (exitCode != 0) throw new RuntimeException("Failed to start GUI container");

        exitCode = runCommand(
            "BRANCH=DEV_V0.9.3 docker-compose -f docker-compose-hascoapi-development.yml up --build -d",
            "Start API Container"
        );
        if (exitCode != 0) throw new RuntimeException("Failed to start API container");

        System.out.println("All Docker containers started successfully.");
    }

    private static int runCommand(String command, String stepName) throws Exception {
        ProcessBuilder builder;

        if (isWindows()) {
            if (command.contains("=")) {
                String[] parts = command.split(" ", 2);
                String var = parts[0].trim();
                String rest = parts[1].trim();
                String setCmd = "set " + var + " && " + rest;
                builder = new ProcessBuilder("cmd.exe", "/c", setCmd);
            } else {
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

    private static boolean isWindows() {
        return System.getProperty("os.name").toLowerCase().contains("win");
    }
}
