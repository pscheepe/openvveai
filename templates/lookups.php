<?php
// New generic choices; no values or records from the old VvE are imported.
return [
    'contacts' => [
        'Status'=>['Actief','Niet actief'], 'Aanhef'=>['De heer','Mevrouw'],
        'VVEFunctie'=>['Voorzitter','Secretaris','Penningmeester','Bestuurslid'],
    ],
    'addresses' => ['AdresSoort'=>['Woonadres','Postadres','Werkadres']],
    'relations' => ['Status'=>['Actief','Niet actief']],
    'Activa' => [
        'ActivaCatID'=>[], 'Status'=>['Actief','Niet actief'],
        'Functie'=>['Hoofdonderdeel','Constructie','Installatie','Ruimte','Toegang','Veiligheid','Energie','Water en riolering','Communicatie'],
        'Toestand'=>['Nieuw','Goed','Redelijk','Matig','Slecht','Buiten gebruik'],
    ],
    'maintenance_plans' => [
        'Status'=>['Gepland','In uitvoering','Afgerond','Vervallen'],
        'OnderhoudsCategorie'=>[], 'StabuCode'=>[],
    ],
    'meetings' => ['Soort'=>['Algemene ledenvergadering','Bestuursvergadering']],
    'projects' => ['Status'=>['groen','oranje','rood']],
    'common' => ['Compleet'=>['Waar','Niet waar']],
];
