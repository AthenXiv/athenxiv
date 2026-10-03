"""Validate an Athenaeum .ots proof with the official OpenTimestamps library.

    python tests/verify_ots.py <proof.ots> <original-file>

Proves that the PHP implementation produced a proof the reference
implementation accepts, and that it commits to the exact bytes of the file.
Exit code 0 means valid.
"""

from __future__ import annotations

import hashlib
import sys

from opentimestamps.core.serialize import BytesDeserializationContext
from opentimestamps.core.timestamp import DetachedTimestampFile

if len(sys.argv) < 3:
    print(__doc__)
    raise SystemExit(2)

proof_path, file_path = sys.argv[1], sys.argv[2]

raw = open(proof_path, "rb").read()
digest = hashlib.sha256(open(file_path, "rb").read()).digest()

ctx = BytesDeserializationContext(raw)
detached = DetachedTimestampFile.deserialize(ctx)

print(f"proof   : {proof_path} ({len(raw)} bytes)")
print(f"file    : {file_path}")
print(f"sha256  : {digest.hex()}")
print(f"proof id: {detached.file_digest.hex()}")

if detached.file_digest != digest:
    print("MISMATCH: the proof does not commit to this file")
    raise SystemExit(1)

# Round-trip: the reference library must be able to re-serialize it identically.
from opentimestamps.core.serialize import BytesSerializationContext  # noqa: E402

out = BytesSerializationContext()
detached.serialize(out)
if out.getbytes() != raw:
    print("MISMATCH: re-serialisation differs from the stored proof")
    raise SystemExit(1)

print("\ntree:")
print(detached.timestamp.str_tree())

attestations = list(detached.timestamp.all_attestations())
pending = [a for _m, a in attestations if a.__class__.__name__ == "PendingAttestation"]
bitcoin = [a for _m, a in attestations if a.__class__.__name__ == "BitcoinBlockHeaderAttestation"]
print(f"\npending attestations : {len(pending)}")
print(f"bitcoin attestations : {len(bitcoin)}")
for _msg, att in attestations:
    if hasattr(att, "uri"):
        print(f"  calendar: {att.uri}")
    elif hasattr(att, "height"):
        print(f"  bitcoin block height: {att.height}")

print("\nVALID: the reference implementation accepts this proof and it matches the file.")
