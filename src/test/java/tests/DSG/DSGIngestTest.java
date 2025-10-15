package tests.DSG;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import tests.base.BaseIngest;

public class DSGIngestTest extends BaseIngest {

    @Test
    @DisplayName("Ingest uploaded DSG file")
    void shouldIngestDSGSuccessfully() throws InterruptedException {
        ingestFile("dsg");
    }
}
