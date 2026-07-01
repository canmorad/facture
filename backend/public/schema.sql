use facture;
INSERT INTO roles (name, description) VALUES
('owner', 'First user who registers. Master access to everything.'),
('manager', 'Full operational access except subscription changes.'),
('accountant', 'Financial/TVA reporting, closing periods, and finalizing access.'),
('assistant-accountant', 'Can create draft invoices/quotes, manage customers.'),
('viewer', 'Read-only access to permitted data screens.');


INSERT INTO permissions (name, description) VALUES
('view-companies', 'View companies'),
('create-companies', 'Create companies'),
('edit-companies', 'Edit companies'),
('delete-companies', 'Delete companies'),
('finalize-companies', 'Finalize companies'),
('export-companies', 'Export companies'),

('view-users', 'View users'),
('create-users', 'Create users'),
('edit-users', 'Edit users'),
('delete-users', 'Delete users'),
('finalize-users', 'Finalize users'),
('export-users', 'Export users'),

('view-customers', 'View customers'),
('create-customers', 'Create customers'),
('edit-customers', 'Edit customers'),
('delete-customers', 'Delete customers'),
('finalize-customers', 'Finalize customers'),
('export-customers', 'Export customers'),

('view-products', 'View products'),
('create-products', 'Create products'),
('edit-products', 'Edit products'),
('delete-products', 'Delete products'),
('finalize-products', 'Finalize products'),
('export-products', 'Export products'),

('view-quotes', 'View quotes'),
('create-quotes', 'Create quotes'),
('edit-quotes', 'Edit quotes'),
('delete-quotes', 'Delete quotes'),
('finalize-quotes', 'Finalize quotes'),
('export-quotes', 'Export quotes'),

('view-invoices', 'View invoices'),
('create-invoices', 'Create invoices'),
('edit-invoices', 'Edit invoices'),
('delete-invoices', 'Delete invoices'),
('finalize-invoices', 'Finalize invoices'),
('export-invoices', 'Export invoices'),

('view-credit_notes', 'View credit notes'),
('create-credit_notes', 'Create credit notes'),
('edit-credit_notes', 'Edit credit notes'),
('delete-credit_notes', 'Delete credit notes'),
('finalize-credit_notes', 'Finalize credit notes'),
('export-credit_notes', 'Export credit notes'),

('view-purchase_orders', 'View purchase orders'),
('create-purchase_orders', 'Create purchase orders'),
('edit-purchase_orders', 'Edit purchase orders'),
('delete-purchase_orders', 'Delete purchase orders'),
('finalize-purchase_orders', 'Finalize purchase orders'),
('export-purchase_orders', 'Export purchase orders'),

('view-delivery_notes', 'View delivery notes'),
('create-delivery_notes', 'Create delivery notes'),
('edit-delivery_notes', 'Edit delivery notes'),
('delete-delivery_notes', 'Delete delivery notes'),
('finalize-delivery_notes', 'Finalize delivery notes'),
('export-delivery_notes', 'Export delivery notes'),

('view-tva_reports', 'View TVA reports'),
('create-tva_reports', 'Create TVA reports'),
('edit-tva_reports', 'Edit TVA reports'),
('delete-tva_reports', 'Delete TVA reports'),
('finalize-tva_reports', 'Finalize TVA reports'),
('export-tva_reports', 'Export TVA reports'),

('view-activity_logs', 'View activity logs'),
('create-activity_logs', 'Create activity logs'),
('edit-activity_logs', 'Edit activity logs'),
('delete-activity_logs', 'Delete activity logs'),
('finalize-activity_logs', 'Finalize activity logs'),
('export-activity_logs', 'Export activity logs');


INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'manager';

-- 3.2 Viewer: gets only 'view-*' permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'viewer'
  AND p.name LIKE 'view-%';

-- 3.3 Accountant: specific permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'accountant'
  AND p.name IN (
    'view-invoices',
    'view-credit_notes',
    'view-tva_reports',
    'view-customers',
    'view-products',
    'view-companies',
    'view-activity_logs',
    'create-invoices',
    'edit-invoices',
    'finalize-invoices',
    'export-invoices',
    'export-credit_notes',
    'export-tva_reports'
  );

-- 3.4 Assistant-accountant: specific permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'assistant-accountant'
  AND p.name IN (
    'view-customers',
    'create-customers',
    'edit-customers',
    'view-products',
    'create-products',
    'edit-products',
    'view-quotes',
    'create-quotes',
    'edit-quotes',
    'view-invoices',
    'create-invoices',
    'edit-invoices',
    'view-companies'
  );