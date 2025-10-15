package tests.DP2;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import tests.base.BaseIngest;

public class DP2IngestTest extends BaseIngest {

    @Test
    @DisplayName("Ingest uploaded DP2 file")
    void shouldIngestDP2Successfully() throws InterruptedException {
        ingestFile("dp2");
    }
}
