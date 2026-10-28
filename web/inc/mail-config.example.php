<?php
// Kopirati u mail-config.php (samo na posluzitelju, ne ide u git) i upisati lozinku.
return array(
    'host'       => 'vps.on-click.hr',
    'port'       => 465,
    'username'   => 'web@lovacki-savez-osijek.hr',
    'password'   => '',                       // lozinka racuna web@lovacki-savez-osijek.hr
    'from'       => 'web@lovacki-savez-osijek.hr',
    'from_name'  => 'Lovački savez OBŽ — web',
    'to'         => 'lovacki.savez.obz@gmail.com',
    'helo'       => 'lovacki-savez-osijek.hr',
    'verify_ssl' => true,
);
