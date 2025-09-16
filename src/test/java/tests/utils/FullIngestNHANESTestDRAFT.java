package tests.utils;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.*;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;

import tests.DSG.DSGIngestTest;
import tests.INS.INSNHANESIngestTest;
import tests.base.BaseIngest;

public class FullIngestNHANESTestDRAFT {

    private final Launcher launcher = LauncherFactory.create();

    @BeforeAll
    static void setMode() {
        BaseIngest.ingestMode = "draft";
    }

    @Test
    void runOnlyIngestsForCurrentMode() throws InterruptedException {

        // INS NHANES
        runTestClass(INSNHANESIngestTest.class);
        Thread.sleep(2000);
        // DSG
        runTestClass(DSGIngestTest.class);
        Thread.sleep(2000);
/*
        // DA
        runTestClass(DAIngestTest.class);
        Thread.sleep(2000);

        // SDD
        runTestClass(SDDIngestDPQTest.class);
        Thread.sleep(2000);
        runTestClass(SDD.SDDIngestDEMOTest.class);
        Thread.sleep(2000);

        // DP2
        runTestClass(DP2IngestTest.class);
        Thread.sleep(2000);
         //STR upload needs to be run after SDD ingest
        runTestClass(STRUploadTest.class);
        Thread.sleep(2000);

        // STR
        runTestClass(STRIngestTest.class);

 */
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
