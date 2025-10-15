package tests.INS;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.*;
import org.junit.platform.launcher.core.*;

public class INSFullIngest {

    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runAllInstrumentTests() throws InterruptedException {
        Thread.sleep(5000);
        runTestClass(INSIngestHierarchyTest.class);
        Thread.sleep(2000);
        runTestClass(INSNHANESIngestTest.class);
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
