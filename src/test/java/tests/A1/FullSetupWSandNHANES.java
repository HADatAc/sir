package tests.A1;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.DiscoverySelector;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.TestExecutionListener;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;
import org.junit.platform.launcher.listeners.SummaryGeneratingListener;

import tests.utils.FullDeleteTest;
import tests.utils.FullIngestNHANESTestDRAFT;
import tests.utils.FullIngestWSTestDRAFT;
import tests.utils.FullUploadNHANESTestALL;
import tests.utils.FullUploadWS;


public class FullSetupWSandNHANES {
    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runOnlyIngestsForCurrentMode() throws InterruptedException {
        // Setup of rep configuration
        /* 
        runTestClassAndAbortOnFailure(RepositoryFormAutomationTest.class);
        Thread.sleep(5000);


        //Admin Status and Data conf permission
        runTestClassAndAbortOnFailure(AdminAuto.class);
        Thread.sleep(5000);

        // All data upload
        runTestClassAndAbortOnFailure(FusekiConnectionTest.class);
        Thread.sleep(5000);

        runTestClassAndAbortOnFailure(BEViaFEStatusSimpleCheck.class);
        Thread.sleep(5000);

        runTestClassAndAbortOnFailure(BEViaFEStatusTest.class);
        Thread.sleep(5000);
        

        runTestClassAndAbortOnFailure(ConfigValidationTest.class);
        Thread.sleep(5000);
*/

        // All data upload
        runTestClassAndAbortOnFailure(FullUploadWS.class);
        Thread.sleep(5000);

        // All data ingest
        runTestClassAndAbortOnFailure(FullIngestWSTestDRAFT.class);
        Thread.sleep(5000);

        // All data upload
        runTestClassAndAbortOnFailure(FullUploadNHANESTestALL.class);
        Thread.sleep(5000);

        // All data ingest
        runTestClassAndAbortOnFailure(FullIngestNHANESTestDRAFT.class);
        Thread.sleep(5000);

        

        // All data Regression Test
        runTestClassAndAbortOnFailure(FullRegressionTest.class);
        Thread.sleep(5000);

        /*//AttachPDFINST
        runTestClassAndAbortOnFailure(AttachPDFINST.class);
        Thread.sleep(5000);

         */

        //Delete
        runTestClassAndAbortOnFailure(FullDeleteTest.class);
        Thread.sleep(5000);

        


    }

    private void runTestClassAndAbortOnFailure(Class<?> testClass) {
        System.out.println("===> Running: " + testClass.getSimpleName());

        TestExecutionListener listener = new SummaryGeneratingListener();
        launcher.execute(
            LauncherDiscoveryRequestBuilder.request()
                .selectors(new DiscoverySelector[]{DiscoverySelectors.selectClass(testClass)})
                .build(),
            listener
        );

        long failures = ((SummaryGeneratingListener) listener).getSummary().getFailures().size();
        if (failures > 0) {
            throw new RuntimeException("Test failed in " + testClass.getSimpleName() + ". Aborting remaining tests.");
        }
    }
}
