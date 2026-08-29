<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Services\FileUploadService;
use Exception;
use Tests\TestCase;

class DocumentTaskAttachmentTest extends TestCase
{
    public function testFileUploadServiceStorageAndFolderHierarchy(): void
    {
        $tmpDir = sys_get_temp_dir();
        $testFile = $tmpDir . '/test_spec_sample.pdf';
        file_put_contents($testFile, '%PDF-1.4 sample pdf content for unit test');

        $fileData = [
            'name' => 'Project Requirements & Spec.pdf',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/pdf',
        ];

        // Store file associated with task ID 5
        $stored = FileUploadService::store($fileData, 5);

        $this->assertEquals('Project Requirements & Spec.pdf', $stored['original_name']);
        $this->assertEquals('pdf', $stored['file_extension']);
        $this->assertEquals('application/pdf', $stored['mime_type']);
        $this->assertTrue(str_starts_with($stored['file_path'], '/uploads/tasks/5/'));

        // Verify physical file existence
        $publicDir = dirname(__DIR__, 2) . '/public';
        $fullPath = $publicDir . $stored['file_path'];
        $this->assertTrue(file_exists($fullPath));

        // Test cleanup
        $deleted = FileUploadService::delete($stored['file_path']);
        $this->assertTrue($deleted);
        $this->assertFalse(file_exists($fullPath));

        if (file_exists($testFile)) {
            @unlink($testFile);
        }
    }

    public function testFileUploadServiceBlockedExtensionSecurity(): void
    {
        $tmpDir = sys_get_temp_dir();
        $badFile = $tmpDir . '/shell.php';
        file_put_contents($badFile, '<?php echo "bad";');

        $fileData = [
            'name' => 'shell.php',
            'tmp_name' => $badFile,
            'size' => filesize($badFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/x-php',
        ];

        $blocked = false;
        try {
            FileUploadService::store($fileData, 5);
        } catch (Exception $e) {
            $blocked = true;
        }

        $this->assertTrue($blocked, 'Executable .php files should be blocked');

        if (file_exists($badFile)) {
            @unlink($badFile);
        }
    }

    public function testMultipleDocumentsAttachedToTask(): void
    {
        // 1. Create a parent task for attachments
        $taskId = Database::insertGetId(
            "INSERT INTO tasks (workspace_id, project_id, status_id, title, priority, created_by, created_at)
             VALUES (2, 1, 1, 'Task with Multi Attachments', 'high', 1, NOW())"
        );
        $this->assertNotNull($taskId);

        // 2. Attach an Image document
        $doc1Id = Database::insertGetId(
            "INSERT INTO documents (workspace_id, project_id, task_id, title, doc_type,
                                    file_path, file_name, original_name, file_size, mime_type, file_extension,
                                    created_by, created_at, updated_at)
             VALUES (2, 1, :task_id, 'App Mockup.png', 'file',
                     '/uploads/tasks/:task_id/mockup.png', 'mockup.png', 'App Mockup.png', 102400, 'image/png', 'png',
                     1, NOW(), NOW())",
            ['task_id' => $taskId]
        );

        // 3. Attach a PDF document
        $doc2Id = Database::insertGetId(
            "INSERT INTO documents (workspace_id, project_id, task_id, title, doc_type,
                                    file_path, file_name, original_name, file_size, mime_type, file_extension,
                                    created_by, created_at, updated_at)
             VALUES (2, 1, :task_id, 'System Architecture.pdf', 'file',
                     '/uploads/tasks/:task_id/arch.pdf', 'arch.pdf', 'System Architecture.pdf', 524288, 'application/pdf', 'pdf',
                     1, NOW(), NOW())",
            ['task_id' => $taskId]
        );

        // 4. Attach a PowerPoint presentation
        $doc3Id = Database::insertGetId(
            "INSERT INTO documents (workspace_id, project_id, task_id, title, doc_type,
                                    file_path, file_name, original_name, file_size, mime_type, file_extension,
                                    created_by, created_at, updated_at)
             VALUES (2, 1, :task_id, 'Sprint Pitch.pptx', 'file',
                     '/uploads/tasks/:task_id/pitch.pptx', 'pitch.pptx', 'Sprint Pitch.pptx', 2097152, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx',
                     1, NOW(), NOW())",
            ['task_id' => $taskId]
        );

        // 5. Query documents attached to this task
        $taskDocs = Database::fetchAll(
            "SELECT d.*, t.title AS task_title
             FROM documents d
             JOIN tasks t ON d.task_id = t.id
             WHERE d.task_id = :task_id
             ORDER BY d.id ASC",
            ['task_id' => $taskId]
        );

        $this->assertEquals(3, count($taskDocs));
        $this->assertEquals('Task with Multi Attachments', $taskDocs[0]['task_title']);
        $this->assertEquals('png', $taskDocs[0]['file_extension']);
        $this->assertEquals('pdf', $taskDocs[1]['file_extension']);
        $this->assertEquals('pptx', $taskDocs[2]['file_extension']);

        // 6. Test detaching a document from the task
        Database::execute("UPDATE documents SET task_id = NULL WHERE id = :id", ['id' => $doc1Id]);
        $remainingDocs = Database::fetchAll("SELECT * FROM documents WHERE task_id = :task_id", ['task_id' => $taskId]);
        $this->assertEquals(2, count($remainingDocs));

        // 7. Cleanup test records
        Database::execute("DELETE FROM documents WHERE id IN (:d1, :d2, :d3)", ['d1' => $doc1Id, 'd2' => $doc2Id, 'd3' => $doc3Id]);
        Database::execute("DELETE FROM tasks WHERE id = :id", ['id' => $taskId]);
    }

    public function testDocumentQueryWithTaskJoinAndFiltering(): void
    {
        // Query seed documents attached to task 5 and 7
        $docs = Database::fetchAll(
            "SELECT d.*, t.title AS task_title
             FROM documents d
             LEFT JOIN tasks t ON d.task_id = t.id
             WHERE d.workspace_id = 2
             ORDER BY d.id ASC"
        );

        $this->assertTrue(count($docs) >= 5);

        // Check file attachment types in seed data
        $fileExtensions = array_filter(array_column($docs, 'file_extension'));
        $this->assertTrue(in_array('png', $fileExtensions));
        $this->assertTrue(in_array('pdf', $fileExtensions));
        $this->assertTrue(in_array('pptx', $fileExtensions));

        // Check task attached titles
        $taskTitles = array_filter(array_column($docs, 'task_title'));
        $this->assertTrue(count($taskTitles) >= 4);
    }

    public function testQueryParamAuthenticationForFileDownloads(): void
    {
        $user = Database::fetchOne("SELECT id, email, full_name, default_mode FROM users WHERE id = 1");
        $this->assertNotNull($user);

        $token = \App\Core\SessionManager::generateToken($user);
        $this->assertNotNull($token);

        // Simulate request with ?token= query parameter
        $_GET['token'] = $token;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = "/api/v1/documents/3/download?token={$token}";

        $request = \App\Core\Request::createFromGlobals();
        $this->assertEquals($token, $request->bearerToken());

        $middleware = new \App\Middleware\AuthMiddleware();
        $authenticated = false;
        $middleware->handle($request, function ($req) use (&$authenticated) {
            $authenticated = true;
            return null;
        });

        $this->assertTrue($authenticated, 'Request with ?token= query param should authenticate successfully');

        unset($_GET['token']);
    }
}
