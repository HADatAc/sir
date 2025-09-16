package tests.utils;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;

import tests.DP2.DP2IngestTest;
import tests.DP2.DP2RegressionTest;
import tests.DP2.DP2UploadTest;
import tests.DSG.DSGIngestTest;
import tests.DSG.DSGRegressionTest;
import tests.INS.INSNHANESIngestTest;
import tests.INS.INSRegressionTest;
import tests.INS.INSUploadTest;
import tests.SDD.SDDIngestWSTest;
import tests.SDD.SDDRegressionTest;
import tests.SDD.SDDUploadTest;
import tests.STR.STRIngestTest;
import tests.STR.STRRegressionTest;
import tests.STR.STRUploadTest;


public class FullWorkflowTest {//extends BaseTest{
/*
1º DSG
2ª SDD
3º DP2
4º STR
    */
    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runFullWorkflowForAllTypes() throws InterruptedException {
        // INS
        runTestClass(INSUploadTest.class);
        Thread.sleep(2000);
        runTestClass(INSNHANESIngestTest.class);
        Thread.sleep(3000);
        runTestClass(INSRegressionTest.class);
        Thread.sleep(3000);

        // DP2
        runTestClass(DP2UploadTest.class);
        Thread.sleep(2000);
        runTestClass(DP2IngestTest.class);
        Thread.sleep(3000);
        runTestClass(DP2RegressionTest.class);
        Thread.sleep(3000);

        // DSG
        runTestClass(STRUploadTest.class);
        Thread.sleep(2000);
        runTestClass(DSGIngestTest.class);
        Thread.sleep(3000);
        runTestClass(DSGRegressionTest.class);
        Thread.sleep(3000);

        // SDD
        runTestClass(SDDUploadTest.class);
        Thread.sleep(2000);
        runTestClass(SDDIngestWSTest.class);
        Thread.sleep(3000);
        runTestClass(SDDRegressionTest.class);
        Thread.sleep(3000);

        // STR
        runTestClass(STRUploadTest.class);
        Thread.sleep(2000);
        runTestClass(STRIngestTest.class);
        Thread.sleep(3000);
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
