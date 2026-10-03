"""Generate reference OpenTimestamps proofs used to validate the PHP codec.

Writes into the directory given as argv[1]:
  pending.ots          - two calendars merged, still pending
  confirmed.ots        - same tree, with a synthetic Bitcoin attestation
  single.ots           - one calendar only
"""
import hashlib
import os
import sys

from opentimestamps.calendar import RemoteCalendar
from opentimestamps.core.notary import BitcoinBlockHeaderAttestation
from opentimestamps.core.op import OpSHA256
from opentimestamps.core.serialize import BytesDeserializationContext, BytesSerializationContext
from opentimestamps.core.timestamp import DetachedTimestampFile, Timestamp
from opentimestamps.core.timestamp import cat_then_unary_op

out_dir = sys.argv[1]
os.makedirs(out_dir, exist_ok=True)

DATA = b"Athenaeum codec validation payload\n" * 7
digest = hashlib.sha256(DATA).digest()
open(os.path.join(out_dir, "payload.bin"), "wb").write(DATA)
print("payload sha256:", digest.hex())


def serialize(detached):
    ctx = BytesSerializationContext()
    detached.serialize(ctx)
    return ctx.getbytes()


def submit(url):
    cal = RemoteCalendar(url, user_agent="Athenaeum/1.0")
    return cal.submit(digest, timeout=25)


aggs = [
    "https://a.pool.opentimestamps.org",
    "https://b.pool.opentimestamps.org",
    "https://a.pool.eternitywall.com",
    "https://ots.btc.catallaxy.com",
]

PENDING = os.path.join(out_dir, "pending.ots")
CONFIRMED = os.path.join(out_dir, "confirmed.ots")


def walk(node):
    yield node
    for child in node.ops.values():
        yield from walk(child)


def reserialize(bytes_):
    """Parse a detached proof and hand back a fresh, independent tree."""
    body = bytes_[65:]  # magic(31) + version(1) + hash-op(1) + digest(32)
    return Timestamp.deserialize(BytesDeserializationContext(body), digest)


if os.path.exists(PENDING):
    print("reusing existing pending.ots (no new calendar submissions)")
    merged = reserialize(open(PENDING, "rb").read())
else:
    stamps = []
    for url in aggs:
        try:
            stamps.append((url, submit(url)))
            print("submitted:", url)
        except Exception as exc:                              # noqa: BLE001
            print("failed:", url, exc)

    if not stamps:
        raise SystemExit("no calendar responded")

    first = stamps[0][1]
    open(os.path.join(out_dir, "single.ots"), "wb").write(
        serialize(DetachedTimestampFile(OpSHA256(), first))
    )

    merged = Timestamp(digest)
    for _url, ts in stamps:
        merged.merge(ts)
    open(PENDING, "wb").write(serialize(DetachedTimestampFile(OpSHA256(), merged)))
    merged = reserialize(open(PENDING, "rb").read())

print("\npending tree:")
print(merged.str_tree())

# ---- synthetic confirmation ------------------------------------------------
# Mirrors what a calendar returns once the aggregation transaction confirms:
# the same tree, plus a BitcoinBlockHeaderAttestation at each pending tip.
confirmed = reserialize(open(PENDING, "rb").read())
heights = [845_123, 845_124, 845_125, 845_126]
for index, node in enumerate(
    n for n in walk(confirmed)
    if any(a.__class__.__name__ == "PendingAttestation" for a in n.attestations)
):
    node.attestations.add(BitcoinBlockHeaderAttestation(heights[index % len(heights)]))

open(CONFIRMED, "wb").write(serialize(DetachedTimestampFile(OpSHA256(), confirmed)))
print("\nconfirmed tree:")
print(confirmed.str_tree())
print("\nwrote single.ots / pending.ots / confirmed.ots into", out_dir)
