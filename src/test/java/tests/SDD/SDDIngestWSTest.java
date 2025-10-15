package tests.SDD;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;

import tests.base.BaseIngest;

public class SDDIngestWSTest extends BaseIngest {

    @Test
    @DisplayName("Ingest SDD file: testeSDDWS")
    void shouldingestsdd() throws InterruptedException {
        ingestSpecificSDD("testeSDDWS");
    }
}
