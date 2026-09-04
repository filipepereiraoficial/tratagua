<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Course;

class CourseController extends Controller
{
    public function index(): void
    {
        $this->view('classes/courses', [
            'title'  => 'Cursos',
            'cursos' => Course::all(),
        ]);
    }

    public function store(): void
    {
        $validator = Validator::make($this->request->post, [
            'name'           => 'required|max:150|unique:courses,name',
            'workload_hours' => 'nullable|integer|min_value:0',
        ], ['name' => 'nome do curso', 'workload_hours' => 'carga horária']);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/cursos');
        }

        Course::create($this->request->post);
        Flash::success('Curso cadastrado.');
        $this->redirect('/cursos');
    }

    public function update(string $id): void
    {
        $courseId = (int) $id;
        if (!Course::find($courseId)) {
            $this->notFound('Curso não encontrado.');
        }

        $validator = Validator::make($this->request->post, [
            'name'           => 'required|max:150|unique:courses,name,' . $courseId,
            'workload_hours' => 'nullable|integer|min_value:0',
        ], ['name' => 'nome do curso', 'workload_hours' => 'carga horária']);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/cursos');
        }

        Course::update($courseId, $this->request->post);
        Flash::success('Curso atualizado.');
        $this->redirect('/cursos');
    }

    public function destroy(string $id): void
    {
        $courseId = (int) $id;
        if (!Course::canDelete($courseId)) {
            Flash::error('Não é possível excluir: existem turmas vinculadas a este curso.');
            $this->redirect('/cursos');
        }
        Course::delete($courseId);
        Flash::success('Curso excluído.');
        $this->redirect('/cursos');
    }
}
