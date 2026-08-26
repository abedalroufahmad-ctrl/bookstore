import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../l10n/app_localizations.dart';
import '../models/user.dart';
import '../providers/auth_provider.dart';

const _allRoles = [
  'manager',
  'shipping',
  'review',
  'accounting',
  'warehouse_manager',
  'publisher_manager',
];

class AdminEmployeesScreen extends StatefulWidget {
  const AdminEmployeesScreen({super.key});

  @override
  State<AdminEmployeesScreen> createState() => _AdminEmployeesScreenState();
}

class _AdminEmployeesScreenState extends State<AdminEmployeesScreen> {
  List<Employee> _employees = [];
  List<Map<String, dynamic>> _warehouses = [];
  List<Map<String, dynamic>> _publishers = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  List<String> _rolesFor(String? myRole) {
    if (myRole == 'warehouse_manager') {
      return const ['shipping', 'accounting'];
    }
    if (myRole == 'publisher_manager') {
      return const [
        'shipping',
        'review',
        'accounting',
        'warehouse_manager',
        'publisher_manager',
      ];
    }
    return _allRoles;
  }

  String _mapId(Map<String, dynamic> m) =>
      (m['_id'] ?? m['id'] ?? '').toString();

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final empF = ApiService.instance.adminEmployeesList();
    final whF = ApiService.instance.adminWarehousesList();
    final pubF = ApiService.instance.adminPublishersList();
    final empRes = await empF;
    final whRes = await whF;
    final pubRes = await pubF;
    if (!mounted) return;
    setState(() {
      _loading = false;
      if (empRes.success && empRes.data != null) {
        _employees = empRes.data!;
      } else {
        _error = empRes.message;
      }
      if (whRes.success && whRes.data != null) {
        _warehouses = whRes.data!;
      }
      if (pubRes.success && pubRes.data != null) {
        _publishers = pubRes.data!;
      }
    });
  }

  Future<void> _confirmDelete(Employee e) async {
    final t = AppLocalizations.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(t.delete),
        content: Text(t.deleteNamed(e.name ?? e.email ?? e.id)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(t.cancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(t.delete),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final res = await ApiService.instance.adminEmployeesDelete(e.id);
    if (!mounted) return;
    if (res.success) {
      _load();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message.isNotEmpty ? res.message : t.error)),
      );
    }
  }

  Future<void> _openForm({Employee? employee}) async {
    final t = AppLocalizations.of(context);
    final auth = context.read<AuthProvider>();
    final myRole = auth.employee?.role;
    final roles = _rolesFor(myRole);

    final nameCtrl = TextEditingController(text: employee?.name ?? '');
    final emailCtrl = TextEditingController(text: employee?.email ?? '');
    final passwordCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();
    var role = employee?.role;
    if (role == null || !roles.contains(role)) {
      role = roles.first;
    }
    String? warehouseId = employee?.warehouseId;
    String? publisherId = employee?.publisherId;
    final warehouseIds = <String>{
      ...?employee?.warehouseIds,
      if (employee?.warehouseId != null && employee!.warehouseId!.isNotEmpty)
        employee.warehouseId!,
    };
    var saving = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheet) {
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
              ),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      employee == null
                          ? (t.isAr ? 'إضافة موظف' : 'Add employee')
                          : (t.isAr ? 'تعديل موظف' : 'Edit employee'),
                      style: Theme.of(ctx).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: nameCtrl,
                      decoration: InputDecoration(labelText: t.nameLabel),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: emailCtrl,
                      decoration: InputDecoration(labelText: t.emailLabel),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: passwordCtrl,
                      obscureText: true,
                      decoration: InputDecoration(
                        labelText: employee == null
                            ? t.passwordLabel
                            : (t.isAr
                                ? 'كلمة المرور (اختياري)'
                                : 'Password (optional)'),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: confirmCtrl,
                      obscureText: true,
                      decoration: InputDecoration(labelText: t.passwordConfirm),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: role,
                      decoration: InputDecoration(labelText: t.adminRole),
                      items: roles
                          .map(
                            (r) => DropdownMenuItem(
                              value: r,
                              child: Text(r.replaceAll('_', ' ')),
                            ),
                          )
                          .toList(),
                      onChanged: (v) {
                        if (v == null) return;
                        setSheet(() => role = v);
                      },
                    ),
                    if (role == 'warehouse_manager') ...[
                      const SizedBox(height: 8),
                      Text(t.adminSelectWarehouse),
                      ..._warehouses.map((w) {
                        final id = _mapId(w);
                        return CheckboxListTile(
                          value: warehouseIds.contains(id),
                          title: Text(w['name']?.toString() ?? id),
                          controlAffinity: ListTileControlAffinity.leading,
                          onChanged: (checked) {
                            setSheet(() {
                              if (checked == true) {
                                warehouseIds.add(id);
                              } else {
                                warehouseIds.remove(id);
                              }
                            });
                          },
                        );
                      }),
                    ] else if (role == 'publisher_manager') ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String?>(
                        initialValue: publisherId,
                        decoration:
                            InputDecoration(labelText: t.adminSelectPublisher),
                        items: [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text(t.adminSelectPublisher),
                          ),
                          ..._publishers.map((p) {
                            final id = _mapId(p);
                            return DropdownMenuItem(
                              value: id,
                              child: Text(p['name']?.toString() ?? id),
                            );
                          }),
                        ],
                        onChanged: (v) => setSheet(() => publisherId = v),
                      ),
                    ] else ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String?>(
                        initialValue: warehouseId,
                        decoration:
                            InputDecoration(labelText: t.adminSelectWarehouse),
                        items: [
                          DropdownMenuItem<String?>(
                            value: null,
                            child: Text(t.adminSelectWarehouse),
                          ),
                          ..._warehouses.map((w) {
                            final id = _mapId(w);
                            return DropdownMenuItem(
                              value: id,
                              child: Text(w['name']?.toString() ?? id),
                            );
                          }),
                        ],
                        onChanged: (v) => setSheet(() => warehouseId = v),
                      ),
                    ],
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: saving
                          ? null
                          : () async {
                              final name = nameCtrl.text.trim();
                              final email = emailCtrl.text.trim();
                              final password = passwordCtrl.text;
                              final confirm = confirmCtrl.text;
                              if (name.isEmpty || email.isEmpty) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(t.fieldRequired)),
                                );
                                return;
                              }
                              if (employee == null && password.isEmpty) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(t.passwordRequired)),
                                );
                                return;
                              }
                              if (password.isNotEmpty && password != confirm) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(t.passwordMismatch)),
                                );
                                return;
                              }

                              final body = <String, dynamic>{
                                'name': name,
                                'email': email,
                                'role': role,
                              };
                              if (password.isNotEmpty) {
                                body['password'] = password;
                                body['password_confirmation'] = confirm;
                              }
                              if (role == 'warehouse_manager') {
                                body['warehouse_ids'] = warehouseIds.toList();
                              } else if (role == 'publisher_manager') {
                                body['publisher_id'] = publisherId;
                              } else {
                                body['warehouse_id'] = warehouseId;
                              }

                              setSheet(() => saving = true);
                              final res = employee == null
                                  ? await ApiService.instance
                                      .adminEmployeesCreate(body)
                                  : await ApiService.instance
                                      .adminEmployeesUpdate(employee.id, body);
                              if (!ctx.mounted) return;
                              setSheet(() => saving = false);
                              if (res.success) {
                                Navigator.pop(ctx);
                                _load();
                              } else {
                                ScaffoldMessenger.of(ctx).showSnackBar(
                                  SnackBar(
                                    content: Text(
                                      res.message.isNotEmpty
                                          ? res.message
                                          : t.adminFailedSave,
                                    ),
                                  ),
                                );
                              }
                            },
                      child: saving
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(t.adminSave),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );

    nameCtrl.dispose();
    emailCtrl.dispose();
    passwordCtrl.dispose();
    confirmCtrl.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(t.adminEmployees),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () => _openForm(),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(),
        child: const Icon(Icons.add),
      ),
      body: _loading
          ? Center(child: Text(t.loading))
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!.isNotEmpty ? _error! : t.error),
                      const SizedBox(height: 12),
                      FilledButton(onPressed: _load, child: Text(t.retry)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _employees.isEmpty
                      ? ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          children: [
                            SizedBox(
                              height: MediaQuery.of(context).size.height * 0.4,
                              child: Center(child: Text(t.adminNoItems)),
                            ),
                          ],
                        )
                      : ListView.builder(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.all(16),
                          itemCount: _employees.length,
                          itemBuilder: (context, i) {
                            final e = _employees[i];
                            final subtitle = [
                              e.email,
                              e.role,
                              e.warehouseName,
                              e.publisherName,
                            ]
                                .whereType<String>()
                                .where((s) => s.isNotEmpty)
                                .join(' · ');
                            return Card(
                              margin: const EdgeInsets.only(bottom: 10),
                              child: ListTile(
                                title: Text(e.name ?? e.email ?? e.id),
                                subtitle: Text(subtitle),
                                onTap: () => _openForm(employee: e),
                                trailing: IconButton(
                                  icon: const Icon(Icons.delete_outline),
                                  onPressed: () => _confirmDelete(e),
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
