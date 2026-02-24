<?php

namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    public function all(array $columns = ['*']);

    public function find(string $id);

    public function create(array $data);

    public function update(string $id, array $data);

    public function delete(string $id);

    public function paginate(int $perPage = 15, array $columns = ['*']);
}
