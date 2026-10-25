-- APRide database fixes. Run once after importing apride.sql (safe to run again).

SET NAMES utf8mb4;

-- 1. Use InnoDB so booking, payment and delete actions can run as transactions.
ALTER TABLE `admin` ENGINE=InnoDB;
ALTER TABLE `booking` ENGINE=InnoDB;
ALTER TABLE `co2_report` ENGINE=InnoDB;
ALTER TABLE `co2_tracker` ENGINE=InnoDB;
ALTER TABLE `customer` ENGINE=InnoDB;
ALTER TABLE `driver` ENGINE=InnoDB;
ALTER TABLE `points` ENGINE=InnoDB;
ALTER TABLE `redemption` ENGINE=InnoDB;
ALTER TABLE `reward` ENGINE=InnoDB;
ALTER TABLE `ride` ENGINE=InnoDB;
ALTER TABLE `ride_history` ENGINE=InnoDB;
ALTER TABLE `transaction_report` ENGINE=InnoDB;
ALTER TABLE `user` ENGINE=InnoDB;

-- 2. Wallet amounts need cents, and new customers start at zero (signup failed without a default).
ALTER TABLE `customer`
  MODIFY `wallet` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  MODIFY `points_balance` INT NOT NULL DEFAULT 0;

-- 3. Reward descriptions are text, not numbers.
ALTER TABLE `reward` MODIFY `description` VARCHAR(255) NULL;
UPDATE `reward` SET `description` = NULL WHERE `description` = '0';

-- 4. Drivers saved with status '0' never showed up under Requests; mark them Pending.
UPDATE `driver` SET `status` = 'Pending' WHERE `status` NOT IN ('Approved', 'Rejected', 'Pending');
ALTER TABLE `driver` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'Pending';

-- 5. Room for password hashes (old plain-text passwords are upgraded on next login).
ALTER TABLE `user` MODIFY `password` VARCHAR(255) NOT NULL;

-- 6. Route arrows were saved as a control character because the connection was not UTF-8.
UPDATE `ride`
SET `destination` = REPLACE(`destination`, CHAR(26 USING utf8mb4), '→')
WHERE LOCATE(CHAR(26 USING utf8mb4), `destination`) > 0;

-- 7. Sample data used bcust's user_id (4) instead of their customer_id (2), leaving orphan rows
--    that a newly registered customer would otherwise inherit.
UPDATE `booking` SET `customer_id` = 2
WHERE `customer_id` = 4 AND NOT EXISTS (SELECT 1 FROM `customer` WHERE `customer_id` = 4);
UPDATE `points` SET `customer_id` = 2
WHERE `customer_id` = 4 AND NOT EXISTS (SELECT 1 FROM `customer` WHERE `customer_id` = 4);
UPDATE `redemption` SET `customer_id` = 2
WHERE `customer_id` = 4 AND NOT EXISTS (SELECT 1 FROM `customer` WHERE `customer_id` = 4);
