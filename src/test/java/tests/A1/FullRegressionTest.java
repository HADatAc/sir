package tests.A1;


import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;

import tests.DP2.DP2RegressionTest;
import tests.DSG.DSGRegressionTest;
import tests.SDD.SDDRegressionTest;
import tests.STR.STRRegressionTest;

public class FullRegressionTest {

    private final Launcher launcher = LauncherFactory.create();


    @Test
    void runRegressionTests() throws InterruptedException {
        // INS
        // runTestClass(INSRegressionTest.class);
        // Thread.sleep(2000);

        // DSG
        runTestClass(DSGRegressionTest.class);
        Thread.sleep(2000);

        // DA
        /* Waiting DA Ingest Implementation
        runTestClass(DARegressionTest.class);
        Thread.sleep(2000);

         */

        // SDD
        runTestClass(SDDRegressionTest.class);
        Thread.sleep(2000);

        // DP2
        runTestClass(DP2RegressionTest.class);
        Thread.sleep(2000);

        // STR
        runTestClass(STRRegressionTest.class);
    }

    private void runTestClass(Class<?> testClass) {
        System.out.println("===> Running: " + testClass.getSimpleName());

        launcher.execute(
                LauncherDiscoveryRequestBuilder.request()
                        .selectors(DiscoverySelectors.selectClass(testClass))
                        .build()
        );
    }
}
