<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(string $salonKey = 'rossini'): void
    {
        $products = $salonKey === 'chic' ? $this->chicProducts() : $this->rossiniProducts();

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }

    private function rossiniProducts(): array
    {
        return [
            [
                'name'                => 'Pomata Modellante Media Tenuta',
                'description'         => 'Pomata a base d\'acqua per capelli con finitura opaca e tenuta media. Ideale per stili definiti che restano in forma tutto il giorno.',
                'price'               => 18.00,
                'stock'               => 24,
                'low_stock_threshold' => 5,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Cera Strong Hold con Lucentezza',
                'description'         => 'Cera professionale ad alta tenuta con finitura lucida. Perfetta per ciuffi, pompadour e stili scultorei.',
                'price'               => 16.50,
                'stock'               => 18,
                'low_stock_threshold' => 4,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Shampoo Rinforzante per Uomo',
                'description'         => 'Shampoo quotidiano con estratti di menta e tea tree. Pulisce in profondità, contrasta il prurito e rinforza il fusto del capello.',
                'price'               => 12.00,
                'stock'               => 30,
                'low_stock_threshold' => 8,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Olio da Barba Nutriente',
                'description'         => 'Blend di oli naturali (jojoba, argan, mandorle) per ammorbidire la barba e idratare la pelle sottostante. Profumazione legno di sandalo.',
                'price'               => 22.00,
                'stock'               => 15,
                'low_stock_threshold' => 4,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Balsamo da Barba Modellante',
                'description'         => 'Balsamo leave-in che doma e ammorbidisce anche le barbe più folte. Tiene la forma senza irrigidire.',
                'price'               => 19.00,
                'stock'               => 12,
                'low_stock_threshold' => 3,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Crema da Barba Pre-Rasatura',
                'description'         => 'Crema lubrificante a base di aloe vera che prepara la pelle alla rasatura, riducendo irritazioni e tagli.',
                'price'               => 14.00,
                'stock'               => 20,
                'low_stock_threshold' => 5,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Dopobarba Lenitivo Senza Alcol',
                'description'         => 'Dopobarba in gel ad azione lenitiva e antibatterica. Senza alcol, adatto anche per le pelli più sensibili.',
                'price'               => 15.00,
                'stock'               => 22,
                'low_stock_threshold' => 6,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Tonico Anticaduta Quotidiano',
                'description'         => 'Lozione concentrata con caffeina e biotina per stimolare la crescita e rinforzare le radici. Uso quotidiano.',
                'price'               => 28.00,
                'stock'               => 8,
                'low_stock_threshold' => 3,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Spazzola Kent Pettine da Barbiere',
                'description'         => 'Pettine professionale in corno sintetico, dentatura fine e larga, per styling preciso e scalp massage.',
                'price'               => 9.50,
                'stock'               => 10,
                'low_stock_threshold' => null,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Kit Travel Barba (3 pezzi)',
                'description'         => 'Set da viaggio con olio da barba, balsamo modellante e pettinino in formato ridotto. Ideale come regalo.',
                'price'               => 34.00,
                'stock'               => 6,
                'low_stock_threshold' => 2,
                'in_sale'             => false,
                'active'              => true,
            ],
        ];
    }

    private function chicProducts(): array
    {
        return [
            [
                'name'                => 'Shampoo Ristrutturante Intensivo',
                'description'         => 'Shampoo professionale con cheratina idrolizzata per capelli danneggiati, colorati o trattati. Restituisce morbidezza e lucentezza dal primo utilizzo.',
                'price'               => 18.00,
                'stock'               => 25,
                'low_stock_threshold' => 6,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Maschera Nutriente all\'Argan',
                'description'         => 'Maschera rigenerante ad azione intensiva con olio di argan puro. Ideale per capelli secchi e crespi. Lasciare in posa 10 minuti per un risultato ottimale.',
                'price'               => 24.00,
                'stock'               => 18,
                'low_stock_threshold' => 5,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Siero Anticrespo Frizz Control',
                'description'         => 'Siero leggero a base di aminoacidi che doma il crespo e protegge dall\'umidità. Si applica su capelli umidi prima della piega.',
                'price'               => 22.00,
                'stock'               => 14,
                'low_stock_threshold' => 4,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Spray Termoprotettore 230°',
                'description'         => 'Protezione termica fino a 230°C per piastra, arricciacapelli e phon. Con complesso vitaminico che idrata e illumina.',
                'price'               => 19.50,
                'stock'               => 20,
                'low_stock_threshold' => 5,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Balsamo Riparatore Senza Risciacquo',
                'description'         => 'Leave-in conditioner con burro di karité e proteina della seta. Nutre le punte, facilita la spazzolatura e protegge dalla rottura.',
                'price'               => 20.00,
                'stock'               => 16,
                'low_stock_threshold' => 4,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Olio Illuminante Multi-Uso',
                'description'         => 'Olio secco capelli e corpo con estratto di camelia e vitamina E. Dona luce intensa senza appesantire.',
                'price'               => 26.00,
                'stock'               => 10,
                'low_stock_threshold' => 3,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Lacca Fissante Extra Forte',
                'description'         => 'Lacca professionale a tenuta extra forte con finissage lucido. Fissa la piega per tutta la giornata senza appesantire.',
                'price'               => 14.00,
                'stock'               => 22,
                'low_stock_threshold' => 6,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Cera Styling Capelli Donna',
                'description'         => 'Cera modellante con finitura opaca per definire ricci, onde e stili texturizzati. Tenuta flessibile, non appiccicosa.',
                'price'               => 17.00,
                'stock'               => 12,
                'low_stock_threshold' => 3,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Profumo per Capelli Fresh Blossom',
                'description'         => 'Bruma profumata per capelli con note di gelsomino e muschio bianco. Lascia i capelli fragranti e lucenti per ore.',
                'price'               => 21.00,
                'stock'               => 8,
                'low_stock_threshold' => 2,
                'in_sale'             => true,
                'active'              => true,
            ],
            [
                'name'                => 'Kit Colore Mantenimento Tinta (shampoo + maschera)',
                'description'         => 'Duo professionale per capelli colorati: shampoo color-protect + maschera ravvivante. Prolunga la brillantezza del colore tra un appuntamento e l\'altro.',
                'price'               => 38.00,
                'stock'               => 7,
                'low_stock_threshold' => 2,
                'in_sale'             => false,
                'active'              => true,
            ],
        ];
    }
}
