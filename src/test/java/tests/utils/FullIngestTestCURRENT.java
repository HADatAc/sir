package tests.utils;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;

import tests.DA.DAIngestTest;
import tests.DP2.DP2IngestTest;
import tests.DSG.DSGIngestTest;
import tests.INS.INSFullIngest;
import tests.SDD.SDDIngestWSTest;
import tests.STR.STRIngestTest;
import tests.base.BaseIngest;

public class FullIngestTestCURRENT {

    private final Launcher launcher = LauncherFactory.create();

    @BeforeAll
    static void setMode() {
        BaseIngest.ingestMode = "current";
    }

    @Test
    void runOnlyIngestsForCurrentMode() throws InterruptedException {
        // INS
        runTestClass(INSFullIngest.class);
        Thread.sleep(2000);

        // DSG
        runTestClass(DSGIngestTest.class);
        Thread.sleep(2000);

        // DA
        runTestClass(DAIngestTest.class);
        Thread.sleep(2000);

        // SDD
        runTestClass(SDDIngestWSTest.class);
        Thread.sleep(2000);

        // DP2
        runTestClass(DP2IngestTest.class);
        Thread.sleep(2000);

        // STR
        runTestClass(STRIngestTest.class);
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
