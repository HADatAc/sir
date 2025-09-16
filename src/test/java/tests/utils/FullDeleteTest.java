package tests.utils;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.*;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;
import tests.DA.DADeleteTest;
import tests.DP2.DP2DeleteTest;
import tests.DSG.DSGDeleteTest;
import tests.INS.INSDeleteTest;

public class FullDeleteTest { //extends BaseTest{
    /*
    1º DSG
    2ª SDD
    3º DP2
    4º STR
        */
    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runAllDeleteTests() throws InterruptedException {
        // INS
        runTestClass(INSDeleteTest.class);
        Thread.sleep(2000);

        // DP2
        runTestClass(DP2DeleteTest.class);
        Thread.sleep(2000);

        // DA
        runTestClass(DADeleteTest.class);
        Thread.sleep(2000);

        // DSG
        runTestClass(DSGDeleteTest.class);
        Thread.sleep(2000);


        // SDD
       // runTestClass(SDDDeleteTest.class);
       // Thread.sleep(2000);

        // STR
        //runTestClass(STRDeleteTest.class);
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
