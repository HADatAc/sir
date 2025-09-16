package tests.utils;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.*;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;
import tests.DA.DAUploadTest;
import tests.DP2.DP2UploadTest;
import tests.DSG.DSGUploadTest;
import tests.INS.INSUploadTest;
import tests.SDD.SDDUploadTest;
import tests.STR.STRUploadTest;

public class FullUploadTestDPQ {

    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runOnlyUploadsForDPQ() throws InterruptedException {
        // INS
        runTestClass(INSUploadTest.class);
        Thread.sleep(2000);
        // DSG
        runTestClass(DSGUploadTest.class);
        Thread.sleep(2000);

        // DA (DPQ)
        System.setProperty("daType", "DPQ");
        runTestClass(DAUploadTest.class);
        Thread.sleep(2000);

        // SDD (DPQ)
        System.setProperty("sddType", "DPQ");
        runTestClass(SDDUploadTest.class);
        Thread.sleep(2000);

        // DP2
        runTestClass(DP2UploadTest.class);
        Thread.sleep(2000);

        // STR
        runTestClass(STRUploadTest.class);
        Thread.sleep(2000);
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
