<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Error - {{ error_message }}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
        <link rel="stylesheet" href="https://dev.prismjs.com/themes/prism.css" />
        <style>
            .card,.code-editor-section,.code-header{box-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06)}:root{all:initial;--primary:#4f46e5;--primary-dark:#4338ca;--error:#dc2626;--error-dark:#b91c1c;--text:#1f2937;--text-light:#6b7280;--text-lighter:#9ca3af;--bg:#f9fafb;--bg-dark:#f3f4f6;--border:#e5e7eb;--border-dark:#d1d5db}body{background-color:#edf2f7}.card{border:unset}.error-title{color:var(--error);font-weight:500}.logo{color:var(--primary);font-size:1.5rem}.error-card{border-radius:.5rem;margin-bottom:1.5rem;border:none}.error-card .card-body{padding:1.25rem}.stack-trace{font-family:Menlo,Monaco,Consolas,monospace;font-size:.85rem;background-color:var(--bg-dark);border-radius:.25rem;padding:1rem;max-height:300px;overflow-y:auto}.code-file,.code-line{font-family:"Fira Code",monospace}.code-header,.stack-frame-line{font-size:.8rem}.stack-frame{margin-bottom:.75rem;padding-bottom:.75rem;border-bottom:1px solid var(--border)}.stack-frame:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}.stack-frame-file{color:var(--text-light);font-size:.8rem}.code-line-highlighted .code-line-content,.stack-frame-line{color:var(--error)}.code-header{background:#f8f8f8;color:#1f2937;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center}.code-file{word-wrap:break-word;overflow-wrap:anywhere;white-space:normal}.code-line{display:flex;width:100%;font-size:.85rem;color:#d4d4d4;padding:.125rem 0}.code-line-number{width:40px;text-align:right;padding-right:1rem;user-select:none;color:var(--text-lighter)}.code-line-content{flex:1;white-space:pre;overflow-x:auto}.code-line-highlighted{display:block;width:100%;background-color:rgba(220,38,38,.2)}.btn,.line-number{display:inline-block}.code-line-highlighted .code-line-number{color:var(--error);font-weight:700}.error-meta strong{color:var(--text)}::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-track{background:#f1f1f1;border-radius:4px}::-webkit-scrollbar-thumb{background:#c1c1c1;border-radius:4px}::-webkit-scrollbar-thumb:hover{background:#a8a8a8}.btn{background-color:#dc3545;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-size:16px;margin-top:25px;transition:background-color .3s}.code-editor-section::-webkit-scrollbar,.error-stack::-webkit-scrollbar{width:8px}.code-editor-section::-webkit-scrollbar-track,.error-stack::-webkit-scrollbar-track{background:#eee}.code-editor-section::-webkit-scrollbar-thumb,.error-stack::-webkit-scrollbar-thumb{background:#e74c3c;border-radius:4px}.code-editor-section::-webkit-scrollbar-thumb:hover,.error-stack::-webkit-scrollbar-thumb:hover{background:#c0392b}.code-block{margin:0;padding:0;font-family:monospace;white-space:pre-wrap;word-wrap:break-word}.line-number{width:40px;text-align:right;margin-right:10px;color:#999}.highlight{background-color:#fdd;color:#c0392b;padding:2px 5px}.bg-red{background:#dc3545}.full-width-error{margin-left:-1.25rem;margin-right:-1.25rem;padding-left:1.25rem;padding-right:1.25rem;width:calc(100% + 2.5rem)}.card-body.d-flex.justify-content-between.align-items-start {text-align: left;word-break: break-all;}
        </style>
    </head>

    <body>
        <div class="container py-4">
            <div class="mb-4 d-flex justify-content-between align-items-start">
                <div class="card w-100">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="error-title mb-2">
                                {{ exception_class }} <br />
                                <span class="text-black">{{ error_message }}</span>
                            </h1>
                        </div>
                        <div class="logo">
                            <a href="https://doppar.com">
                                <img src="https://doppar.com/logo.png" alt="Doppar" height="24" />
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 text-end">
                        <span class="badge bg-primary">{{ request_method }}</span>
                        <span class="badge bg-primary">{{ request_url }}</span>
                        <span class="badge bg-primary">{{ timestamp }}</span>
                    </div>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card error-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2 text-danger"></i> Error
                                Details
                            </span>
                            <span class="php-version text-muted small">
                                PHP {{ php_version }}</span>
                        </div>
                        <div class="card-body">
                            <div class="error-meta">
                                <div class="bg-red text-white full-width-error py-2">
                                    <p class="mb-0"><strong class="text-white">Error:</strong> {{ error_message }}</p>
                                </div>
                                <p class="mb-2"><strong>File:</strong> {{ error_file }}</p>
                                <p class="mb-0"><strong>Line:</strong> {{ error_line }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="card error-card">
                        <div class="card-header">
                            <i class="fas fa-server me-2"></i> Environment
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4">PHP</dt>
                                <dd class="col-sm-8">{{ php_version }}</dd>
                                <dt class="col-sm-4">Server</dt>
                                <dd class="col-sm-8">{{ server_software }}</dd>
                                <dt class="col-sm-4">Platform</dt>
                                <dd class="col-sm-8">{{ platform }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="card error-card">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas fa-layer-group me-2"></i> Stack Trace
                        </div>
                        <div class="card-body p-0">
                            <div class="stack-trace">
                                <div class="stack-frame">
                                    <div class="stack-frame-line">{{ error_trace }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="code-editor h-100">
                        <div class="code-header">
                            <div class="code-file">
                                <i class="fas fa-file-code me-2"></i>{{ error_file }}
                            </div>
                            <div>
                                <span class="badge bg-danger">
                                    <i class="fas fa-exclamation me-1"></i> Line {{ error_line }}
                                </span>
                            </div>
                        </div>
                        <div class="code-editor-section">
                            <div class="error-container">
                                <div class="code-editor-section">
                                    <pre><code class="language-php">{{ file_content }}</code></pre>
                                </div>
                            </div>
                        </div>
                        <a href="/" class="btn"> <i class="fas fa-home me-2"></i> Home </a>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    </body>

</html>
