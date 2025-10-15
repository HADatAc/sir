package tests.config;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;
import tests.utils.InstrumentAddPdfDemoTest;
import tests.utils.InstrumentAddPdfTest;

public class AttachPDFINST {
    private final Launcher launcher = LauncherFactory.create();

    @Test
    void attachpdfs() throws InterruptedException {
        // DEMO
        runTestClass(InstrumentAddPdfDemoTest.class);
        Thread.sleep(5000);
        // DPQ
        runTestClass(InstrumentAddPdfTest.class);
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
