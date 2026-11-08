<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controller;
use App\Flash;
use App\Request;
use App\Response;
use App\Repositories\LibraryRepository;
use App\Repositories\StudentRepository;
use App\Repositories\TeacherRepository;

final class LibraryController extends Controller
{
    private LibraryRepository $repository;

    public function __construct(?LibraryRepository $repository = null)
    {
        $this->repository = $repository ?? new LibraryRepository();
    }

    public function index(Request $request): void
    {
        $books = $this->repository->allBooks();
        $this->view('library/index', ['books' => $books]);
    }

    public function create(Request $request): void
    {
        $this->view('library/create');
    }

    public function store(Request $request): void
    {
        $title = trim((string) $request->post('title'));
        $author = trim((string) $request->post('author'));

        if ($title === '' || $author === '') {
            Flash::error(__('library.missing_fields'));
            Response::redirect('/library/create');
            return;
        }

        $id = $this->repository->createBook([
            'isbn'           => trim((string) $request->post('isbn')) ?: null,
            'title'          => $title,
            'author'         => $author,
            'category'       => trim((string) $request->post('category')) ?: 'General',
            'total_copies'   => (int) $request->post('total_copies', 1),
            'shelf_location' => trim((string) $request->post('shelf_location')) ?: null,
        ]);

        Flash::success(__('library.created_success'));
        Response::redirect('/library');
    }

    public function borrows(Request $request): void
    {
        $status = $request->query('status');
        $borrows = $this->repository->allBorrows($status ? (string) $status : null);
        $books = $this->repository->allBooks();
        $students = (new StudentRepository())->all();
        $teachers = (new TeacherRepository())->all();

        $this->view('library/borrows', [
            'borrows'      => $borrows,
            'books'        => $books,
            'students'     => $students,
            'teachers'     => $teachers,
            'activeStatus' => $status,
        ]);
    }

    public function borrow(Request $request): void
    {
        $bookId = (int) $request->post('book_id');
        $borrowerType = (string) $request->post('borrower_type', 'student');
        $studentId = $request->post('student_id') ? (int) $request->post('student_id') : null;
        $teacherId = $request->post('teacher_id') ? (int) $request->post('teacher_id') : null;
        $dueDate = (string) $request->post('due_date', date('Y-m-d', strtotime('+14 days')));

        if ($bookId <= 0) {
            Flash::error(__('library.missing_book'));
            Response::redirect('/library/borrows');
            return;
        }

        $this->repository->recordBorrow($bookId, $borrowerType, $studentId, $teacherId, $dueDate);
        Flash::success(__('library.borrow_recorded'));
        Response::redirect('/library/borrows');
    }

    public function returnBook(Request $request, string $id): void
    {
        $this->repository->recordReturn((int) $id);
        Flash::success(__('library.return_recorded'));
        Response::redirect('/library/borrows');
    }
}
