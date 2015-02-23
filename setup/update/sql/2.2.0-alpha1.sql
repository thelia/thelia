set foreign_key_checks = 0;

update `config` set `value`='2.2.0-alpha1' where `name`='thelia_version';
update `config` set `value`='2' where `name`='thelia_major_version';
update `config` set `value`='2' where `name`='thelia_minus_version';
update `config` set `value`='0' where `name`='thelia_release_version';
update `config` set `value`='alpha1' where `name`='thelia_extra_version';

-- admin hooks

select @max_id := ifnull(max(`id`),0) from `hook`;

insert into `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) values
  (@max_id + 1, 'order.tab', 2, 0, 1, 1, 1, 1, now(), now())
;

insert into  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) values
  (@max_id + 1, 'fr_fr', 'commande - onglet', '', ''),
  (@max_id + 1, 'en_us', 'order - tab', '', '')
;

select @max_id := max(`id`) from `order_status`;

insert into `order_status` values
  (@max_id + 1, "refunded", now(), now())
;

insert into  `order_status_i18n` values
  (@max_id + 1, "en_us", "refunded", "", "", ""),
  (@max_id + 1, "fr_fr", "remboursée", "", "", "")
;

-- new column in admin_log

alter table `admin_log` add `resource_id` integer after `resource` ;

-- new config

select @max_id := ifnull(max(`id`),0) from `config`;

insert into `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) values
(@max_id + 1, 'customer_change_email', '0', 0, 0, now(), now()),
(@max_id + 2, 'customer_confirm_email', '0', 0, 0, now(), now())
;

insert into `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) values
(@max_id + 1, 'en_us', 'allow customers to change their email. 1 for yes, 0 for no', null, null, null),
(@max_id + 1, 'fr_fr', 'permettre aux clients de changer leur email. 1 pour oui, 0 pour non', null, null, null),
(@max_id + 2, 'en_us', 'ask the customers to confirm their email, 1 for yes, 0 for no', null, null, null),
(@max_id + 2, 'fr_fr', 'demander aux clients de confirmer leur email. 1 pour oui, 0 pour non', null, null, null)
;

-- country area table

create table `country_area`
(
    `country_id` integer not null,
    `area_id` integer not null,
    `created_at` datetime,
    `updated_at` datetime,
    index `country_area_area_id_idx` (`area_id`),
    index `fk_country_area_country_id_idx` (`country_id`),
    constraint `fk_country_area_area_id`
        foreign key (`area_id`)
        references `area` (`id`)
        on update restrict
        on delete cascade,
    constraint `fk_country_area_country_id`
        foreign key (`country_id`)
        references `country` (`id`)
        on update restrict
        on delete cascade
) engine=innodb character set='utf8';

-- Initialize the table with existing data
INSERT INTO `country_area` (`country_id`, `area_id`, `created_at`, `updated_at`) select `id`, `area_id`, NOW(), NOW() FROM `country` WHERE area_id <> NULL

-- Remove area_id column from country table
ALTER TABLE `country` DROP `area_id`;

set foreign_key_checks = 1;