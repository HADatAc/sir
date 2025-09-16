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

public class FullUploadNHANESTestALL {

    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runOnlyUploadsForDPQ() throws InterruptedException {
        // INS
        System.setProperty("insType", "nhanes");
        runTestClass(INSUploadTest.class);
        Thread.sleep(2000);

        // DSG
        System.setProperty("dsgType", "nhanes");
        runTestClass(DSGUploadTest.class);
        Thread.sleep(2000);
/*
        // DA (DEMO)
        System.setProperty("daType", "DEMO");
        runTestClass(DAUploadTest.class);
        Thread.sleep(2000);


        // DA (DPQ)
        System.setProperty("daType", "DPQ");
        runTestClass(DAUploadTest.class);
        Thread.sleep(2000);



         //SDD (DEMO)
        System.setProperty("sddType", "DEMO");
        runTestClass(SDDUploadTest.class);
        Thread.sleep(2000);
        // SDD (DPQ)
        System.setProperty("sddType", "DPQ");
        runTestClass(SDDUploadTest.class);
        Thread.sleep(2000);


        // DP2
        runTestClass(DP2UploadTest.class);
        Thread.sleep(2000);

        // STR
        //runTestClass(STRUploadTest.class);
        //Thread.sleep(2000);

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
