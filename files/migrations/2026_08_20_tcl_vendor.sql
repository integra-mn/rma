-- TCL as a real vendor, so the adapter framework can reach it.
--
-- vendors and vendor_adapters have been empty since they were created: the
-- Apple settings card reads a row that does not exist and silently saves
-- nothing. TCL needs one to work at all, because vendor_adapter() resolves the
-- class from vendor_adapters.adapter_class.
--
-- Seeded INACTIVE and with no credentials. Nothing happens until Rajo enters
-- Integra's own SCS login in Podesavanja -> Integracije -> TCL and switches it
-- on. The shared logins printed in TCL's booklet are deliberately not used:
-- they belong to every repair centre in the region and will be rotated.
--
-- Apple is deliberately NOT seeded. Its adapter still returns invented
-- warranty answers, and a row would let somebody switch it on and be told a
-- device is covered when nobody has asked Apple anything.

INSERT INTO vendors (name, slug, is_active)
SELECT 'TCL', 'tcl', 1
 WHERE NOT EXISTS (SELECT 1 FROM vendors WHERE slug = 'tcl');

-- Production endpoint from TCL's own spec. UAT is https://uatcsm.tcl.com:5560
-- and is worth using for the first test, since a wrong ticket header there
-- costs nothing.
INSERT INTO vendor_adapters (vendor_id, adapter_class, endpoint_url, auth_type, credentials, sync_mode, is_active)
-- 'custom', not 'ticket': the column has a check constraint listing oauth2,
-- api_key, basic and custom, and TCL's two-step RSA-then-ticket login is none
-- of the first three. Widening the constraint for one vendor would be the
-- wrong trade - 'custom' is what it is for.
SELECT v.id, 'TclAdapter', 'https://csm.tclcom.com:5560', 'custom', '{}', 'manual', 0
  FROM vendors v
 WHERE v.slug = 'tcl'
   AND NOT EXISTS (SELECT 1 FROM vendor_adapters a WHERE a.vendor_id = v.id);
