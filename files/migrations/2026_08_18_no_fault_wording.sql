-- One name for one thing.
--
-- The repair job said "Kvar nije uočen", the case status added yesterday said
-- "Bez kvara", and the two mean exactly the same. Rajo: two terms for one
-- outcome is confusing. Kept the shorter one — it reads as a pill, and "Nema
-- kvara" is what people say out loud.
--
-- The two rows are different levels of the same case, not two different facts:
-- the job is what the bench did, the case is where the whole RMA stands. They
-- are now spelled identically on purpose, so seeing the same words twice reads
-- as agreement rather than as a distinction nobody explained.

UPDATE rma_statuses
   SET label = 'No fault found', label_me = 'Nema kvara'
 WHERE code = 'no_fault';

UPDATE repair_statuses
   SET label = 'No fault found', label_me = 'Nema kvara'
 WHERE code = 'no_fault_found';
