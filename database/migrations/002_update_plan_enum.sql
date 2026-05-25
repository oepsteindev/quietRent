-- Update plan enum to include 'pro' and remove unused plans
ALTER TABLE accounts MODIFY plan ENUM('trial','starter','pro') NOT NULL DEFAULT 'trial';
