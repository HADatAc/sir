package tests.A1;

import org.junit.jupiter.api.Test;
import org.junit.platform.engine.discovery.DiscoverySelectors;
import org.junit.platform.launcher.Launcher;
import org.junit.platform.launcher.core.LauncherDiscoveryRequestBuilder;
import org.junit.platform.launcher.core.LauncherFactory;

import tests.INS.INSDeleteTest;
import tests.INS.INSNHANESIngestTest;
import tests.INS.INSUploadTest;
import tests.config.AdminAuto;
import tests.config.BackendUPTest;
import tests.config.UninstallPmsrModuleTest;
import tests.repository.DeleteALLTriplesTest;
import tests.repository.NamespaceTableVerificationTest;
import tests.repository.NamespacesNHANESTest;
import tests.repository.NoNamespacesTest;
import tests.repository.ReloadALLTriplesTest;
import tests.repository.RepositoryFormAutomationTest;
/*
        *Teste de Regressão para as ontologias do repositório testando:
        * - Reset do Docker
        * - Tabela de Namespaces das ontologias
        * - Reload de todas as triples
        * - Delete de todas as triples
        * - Namespaces com o INS do NHANES
        * - Reload das triplas do INS do NHANES
        * - Delete das triplas do INS do NHANES
        * - Delete do INS do NHANES
 */

public class RegressionOntologiesTest {
    private final Launcher launcher = LauncherFactory.create();

    @Test
    void runsetupanddeletetests() throws InterruptedException {


       /*  runTestClass(ResetDockerTest.class);
        Thread.sleep(5000);
        */


        runTestClass(BackendUPTest.class);
        Thread.sleep(5000);

        runTestClass(UninstallPmsrModuleTest.class);
        Thread.sleep(5000);

        runTestClass(NoNamespacesTest.class);
        Thread.sleep(5000);

        runTestClass(RepositoryFormAutomationTest.class);
        Thread.sleep(5000);

        runTestClass(AdminAuto.class);
        Thread.sleep(5000);

        runTestClass(NamespaceTableVerificationTest.class);
        Thread.sleep(5000);

        runTestClass(ReloadALLTriplesTest.class);
        Thread.sleep(5000);

        runTestClass(DeleteALLTriplesTest.class);
        Thread.sleep(5000);

        System.setProperty("insType", "nhanes");
        runTestClass(INSUploadTest.class);
        Thread.sleep(5000);

        runTestClass(INSNHANESIngestTest.class);
        Thread.sleep(5000);

        runTestClass(NamespacesNHANESTest.class);
        Thread.sleep(5000);

        runTestClass(INSDeleteTest.class);
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
