package tests.INS;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.*;
import org.junit.platform.launcher.core.*;

public class INSFullWorkflowTest {

    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runAllInstrumentTests() throws InterruptedException {
        runTestClass(INSUploadTest.class);
        Thread.sleep(2000);

        runTestClass(INSIngestHierarchyTest.class);
        Thread.sleep(2000);
        runTestClass(INSNHANESIngestTest.class);
        Thread.sleep(2000);
       // runTestClass(INSRegressionTest.class);
        //Thread.sleep(3000);

        //runTestClass(INSDeleteTest.class);
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
