<?php
namespace App\Models;

class Specialty extends BaseModel
{
    protected string $table = 'specialties';

    public function getAllActive(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM specialties ORDER BY name ASC"
        );
    }

    public function getAllGrouped(): array
    {
        $all = $this->getAllActive();
        
        $db = \App\Helpers\Database::getInstance();
        $catalogTypes = $db->fetchAll("SELECT code, name FROM catalog_types");
        $typeNames = [];
        foreach($catalogTypes as $ct) {
            $typeNames[$ct['code']] = $ct['name'];
        }

        $grouped = [];
        foreach($all as $s) {
            $code = $s['catalog_type_code'] ?? 'uncategorized';
            $groupName = $typeNames[$code] ?? 'Consulta Médica / Otros';
            
            if(!isset($grouped[$code])) {
                $grouped[$code] = [
                    'name' => $groupName,
                    'items' => []
                ];
            }
            $grouped[$code]['items'][] = $s;
        }

        return $grouped;
    }

    public function paginate(int $page = 1, int $perPage = 25, string $orderBy = 'name ASC', string $where = '1=1', array $params = []): array
    {
        return parent::paginate($page, $perPage, $orderBy, $where, $params);
    }
}
