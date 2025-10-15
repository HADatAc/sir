package tests.utils;

import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;

import  tests.DSG.DSGIngestTest;
import tests.SDD.SDDIngestWSTest;
import tests.base.BaseIngest;

public class FullIngestWSTestDRAFT {

    private final Launcher launcher = LauncherFactory.create();

    @BeforeAll
    static void setMode() {
        BaseIngest.ingestMode = "draft";
    }

    @Test
    void runOnlyIngestsForCurrentMode() throws InterruptedException {


        // SDD
        runTestClass(SDDIngestWSTest.class);
        Thread.sleep(5000);

        // DSG
        runTestClass(DSGIngestTest.class);
        Thread.sleep(5000);


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
