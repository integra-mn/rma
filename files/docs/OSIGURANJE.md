# Osiguranje — insurance claims

**Status: design notes only. Nothing is built.** Recorded 2026-08-15 from a
working session with Rajo. Held until the two insurers answer the questions at
the bottom, in particular whether their portals offer an API.

Devices arrive from partners (Mtel, One, Telekom, Tehnomax, Multicom) with
advance notice that they are insured. Damage is reported into the insurer's own
web portal — Integra has no automated route today. Two insurers, one insurer per
partner. One person in the office follows claims.

## Why this is not a status on the RMA

The obvious move is to add statuses like "Prijavljeno osiguranju" to the
thirteen in `rma_statuses`. It does not work, for three reasons:

1. **The clocks do not line up.** Payment lands weeks after the device is
   repaired and returned. The RMA is closed and gone while the claim is still
   open. One `status_id` cannot say "repaired, claim still open".
2. **Theft has no device.** Nothing arrives, nothing is diagnosed, nobody
   collects anything. Forced into an RMA it sits in "Čeka se uredjaj" for ever
   and the tracking page tells the customer something untrue.
3. **The payer changes.** `invoices` knows a customer and a partner. On an
   insured repair most of the bill goes to the insurer.

So a claim is its own record that *may* point at an RMA. Theft is a claim with
no RMA; a cracked screen is an RMA with a claim beside it.

## The model

Three layers. The middle one is the important one.

| Layer | What it is | Notes |
|---|---|---|
| **Product** | What an insurer sells | Only a template. Picking it pre-fills coverage, participation and allowance. |
| **Policy** | One customer's device, insured, with a period | **The record the app checks against.** |
| **Claim** | One event drawn against a policy | A policy may have several. |

**The policy holds the truth, not the product.** Samples vary between policies —
one covers screen only, another screen + battery + frame — so whoever enters the
policy corrects whatever the paper actually says. The product only saves typing.

### Coverage is a list, not a type

There is no clean Full/Limited enum to write. What a policy covers is a **list
of ticked items** — screen, battery, frame, liquid, theft — and "Full" and
"Limited" are just the names people use for common combinations. Theft is noted
separately on the policy document and is independent of the rest.

That item list should be admin-editable, the same as RMA statuses, so a new
sample from either insurer needs no code.

### Renewal does not exist

Insurers issue another policy rather than renewing one, so every renewal is a
**new record**. Nothing resets and nothing is edited in place; last year's
claims stay attached to last year's policy and the history stays honest.

**Consequence:** a device accumulates several policies over the years, so the
app must never grab the newest one. It picks the policy **whose period contains
the incident date**.

## The intake check — the part that pays for itself

Refusals are administrative, not about the kind of damage: expired policy,
allowance used up, damage not on this policy, no police report on a theft. Every
one of those is knowable at the counter, before the device is accepted.

Reception scans the IMEI and the app answers four questions in order:

1. Is there a policy for this device?
2. Was the **incident date** inside its period?
3. Is **this damage** on **this policy's** list? (liquid on a screen-only policy → no)
4. Is there allowance left?

Any one failing means it is an ordinary paid repair, and the customer hears so
at the counter instead of a week later.

The panel should read something like:

> Polisa 12345 — ekran, baterija. 10% učešće. Važi do 14-03-2027.
> **1 odobrena, 1 u obradi, od 2 dozvoljene.**

### Two counting rules

**Only approved claims consume allowance.** A refusal costs the customer
nothing.

**A pending claim is neither.** It has consumed nothing, but it cannot be
treated as free either or reception would promise cover on a third claim while
the second is still undecided. Hence both numbers on the panel.

### Our count may not be the whole truth

The app can only count claims Integra handled. A claim made directly with the
insurer, or through another service centre, is invisible to us. Until we know
whether the portal exposes a policy's history, that number is **our record**,
not fact, and should be worded that way on screen. A counter that quietly lies
at the counter is worse than one that admits what it does not know.

## Dates

Three dates, and people confuse them constantly:

| Date | Meaning |
|---|---|
| Incident | When the damage happened. **Decides coverage.** |
| Reported | When we told the insurer, through the portal. |
| Report due | Deadline, from the insurer's window. |

The check is always against the **incident date**, never today. A device damaged
on the 10th, brought in on the 20th, on a policy ending the 15th, is normally
still covered. An app checking against today would refuse valid claims and
nobody would notice for months.

## The claim's life

```
nova → prijavljena → (dopuna) → odobrena / odbijena → isplaćena → zatvorena
```

**"Dopuna" is the state everyone forgets** — the insurer has asked for another
photo or a technician's finding and is waiting on *us*. Without its own state it
looks identical to "reported and waiting", and it is the one that quietly rots.

### Diagnosis is allowed, repair is not

The insurer wants to know what the repair will cost before approving, so the
technician must diagnose and quote **before** approval. The hold is on ordering
parts and repairing, not on looking at the device.

This sits on top of the reception/servis status split deployed 2026-08-15: the
technician owns the bench statuses, but on an insured case the decision to
proceed is not theirs.

### "Čeka se odobrenje" becomes ambiguous

The status already exists and means the customer is deciding. On an insured case
the insurer is deciding. Same status, different counterparty, and the message
the customer should receive is not the same sentence. To be settled when the
wording is written in Sabloni.

## Money

Participation is **10%, or whatever that policy says** — of the repair cost
**including PDV** — and **Integra collects it from the customer** at handover.

Repair of 200 € including PDV: insurer invoiced 180 €, customer pays 20 €. Both
invoices point at the same claim, so the policy history shows what the repair
cost and what each side actually paid.

`invoices` currently has `customer_id` and `partner_id`; an insurer is a third
kind of payer and has no column yet.

## Theft

No device, no bench, no collection — the repair side is irrelevant. What Integra
does is report the claim and gather the police report, the IMEI and proof of
purchase.

**Unresolved:** how a theft claim ends. If the insurer pays the customer,
Integra's part ends with the paperwork. If the outcome is a replacement device
and Integra supplies it, that is a separate piece of work — stock, delivery,
registering the new IMEI. This is the largest unknown in the design.

## The handler's queue

One person in the office follows claims, so they get their own screen: what must
be reported today, what is waiting on the insurer, what is waiting on us
(dopuna), what is approved but unpaid.

Its own permission module (`claims` — view/create/edit) rather than riding on
`rma.edit`; this is back-office work, not bench work. And it needs a deputy or
admin fallback, for the same reason admins bypass the status split — one person
is a single point of failure and they take holidays.

## The API question

Build the reporting step as a **recorded action** first — prijavljeno, this
date, this claim number, by this person. If an API appears later, the same
action gets an automatic path behind it and nothing else changes.

There is a precedent in the codebase: `adapters/VendorAdapter.php` defines the
operations, each vendor implements them, and which one to use comes from
`vendor_adapters.adapter_class`. An insurer adapter would follow that shape.

## To ask the two insurers

1. **Does the portal have an API, and would you give us credentials?** Decides
   months of work. Ask before anything is built.
2. **Can we see a policy's full claim history** through the portal? Decides
   whether the intake panel shows a real number or our own best guess.
3. **What is the reporting window**, and does it run from the incident or from
   when we receive the device?
4. **On a theft claim, what is the outcome** — payout to the customer, or a
   replacement device, and is Integra expected to supply it?
5. **Does a claim reported and then withdrawn** count against the allowance?
   (Refused ones do not — confirmed.)

## Also settled elsewhere

Status notifications are currently switched off on the server — every row in
`rma_statuses` has `notify = 0`, and `notify_rma_status()` returns early on that
flag. Nothing insurance-related should assume a customer is being messaged until
that is put back.
