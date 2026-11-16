<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use PDO;

final class TransportRepository
{
    public function allRoutes(): array
    {
        $stmt = Database::connection()->query(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM student_transport st WHERE st.route_id = r.id AND st.status = \'active\') AS assigned_students
             FROM transport_routes r
             ORDER BY r.route_name ASC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public function findRoute(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM transport_routes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createRoute(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO transport_routes (
                route_name, vehicle_number, driver_name, driver_phone, capacity, fee_amount, is_active, created_at
             ) VALUES (
                :name, :vehicle, :driver, :phone, :capacity, :fee, TRUE, :now
             )'
        );
        $stmt->execute([
            'name'     => $data['route_name'],
            'vehicle'  => $data['vehicle_number'],
            'driver'   => $data['driver_name'],
            'phone'    => $data['driver_phone'],
            'capacity' => $data['capacity'] ?? 30,
            'fee'      => $data['fee_amount'] ?? 0.00,
            'now'      => date('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function getStudentsForRoute(int $routeId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT st.*, s.student_code, s.full_name, s.phone, s.address,
                    c.name AS class_name
             FROM student_transport st
             JOIN students s ON s.id = st.student_id
             LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.status = \'active\'
             LEFT JOIN classes c ON c.id = se.class_id
             WHERE st.route_id = :route_id
             ORDER BY s.full_name ASC'
        );
        $stmt->execute(['route_id' => $routeId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Put a student on a route.
     *
     * Capacity is enforced: a bus with 30 seats must not accept a 31st child
     * because nobody counted. Re-assigning a student who is already active on
     * the route is allowed and does not consume a second seat.
     *
     * @throws \RuntimeException route_not_found|route_full
     */
    public function assignStudent(int $studentId, int $routeId, ?string $pickup = null, ?string $dropoff = null): void
    {
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $routeStmt = $pdo->prepare(
                'SELECT id, capacity FROM transport_routes WHERE id = :id FOR UPDATE'
            );
            $routeStmt->execute(['id' => $routeId]);
            $route = $routeStmt->fetch();
            if ($route === false) {
                throw new \RuntimeException('route_not_found');
            }

            $existingStmt = $pdo->prepare(
                'SELECT id, status FROM student_transport
                 WHERE student_id = :s AND route_id = :r FOR UPDATE'
            );
            $existingStmt->execute(['s' => $studentId, 'r' => $routeId]);
            $existing = $existingStmt->fetch();
            $alreadyActive = $existing !== false && (string) $existing['status'] === 'active';

            if (!$alreadyActive) {
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM student_transport WHERE route_id = :r AND status = 'active'"
                );
                $countStmt->execute(['r' => $routeId]);
                if ((int) $countStmt->fetchColumn() >= (int) $route['capacity']) {
                    throw new \RuntimeException('route_full');
                }
            }

            $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                ? 'INSERT INTO student_transport (student_id, route_id, pickup_point, dropoff_point, status, created_at)
                   VALUES (:s, :r, :pick, :drop, :status, :now)
                   ON CONFLICT (student_id, route_id) DO UPDATE SET
                   pickup_point = EXCLUDED.pickup_point, dropoff_point = EXCLUDED.dropoff_point, status = EXCLUDED.status'
                : 'INSERT INTO student_transport (student_id, route_id, pickup_point, dropoff_point, status, created_at)
                   VALUES (:s, :r, :pick, :drop, :status, :now)
                   ON DUPLICATE KEY UPDATE
                   pickup_point = VALUES(pickup_point), dropoff_point = VALUES(dropoff_point), status = VALUES(status)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                's'      => $studentId,
                'r'      => $routeId,
                'pick'   => $pickup,
                'drop'   => $dropoff,
                'status' => 'active',
                'now'    => date('Y-m-d H:i:s'),
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
