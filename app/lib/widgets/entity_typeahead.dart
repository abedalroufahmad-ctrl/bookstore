import 'dart:async';

import 'package:flutter/material.dart';

/// Search-as-you-type picker with chips and optional "create new" action.
class EntityTypeahead extends StatefulWidget {
  const EntityTypeahead({
    super.key,
    required this.label,
    required this.hint,
    required this.selected,
    required this.onSearch,
    required this.onSelect,
    required this.onRemove,
    this.onCreate,
    this.createLabelBuilder,
    this.enabled = true,
  });

  final String label;
  final String hint;
  final List<({String id, String label})> selected;
  final Future<List<({String id, String label})>> Function(String query) onSearch;
  final void Function(String id, String label) onSelect;
  final void Function(String id) onRemove;
  final Future<void> Function(String name)? onCreate;
  final String Function(String name)? createLabelBuilder;
  final bool enabled;

  @override
  State<EntityTypeahead> createState() => _EntityTypeaheadState();
}

class _EntityTypeaheadState extends State<EntityTypeahead> {
  final _ctrl = TextEditingController();
  final _focus = FocusNode();
  Timer? _debounce;
  bool _open = false;
  bool _loading = false;
  bool _creating = false;
  List<({String id, String label})> _results = [];

  @override
  void initState() {
    super.initState();
    _focus.addListener(() {
      if (_focus.hasFocus) {
        setState(() => _open = true);
        _runSearch(_ctrl.text);
      }
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _ctrl.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _scheduleSearch(String raw) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 280), () {
      _runSearch(raw);
    });
  }

  Future<void> _runSearch(String raw) async {
    if (!mounted) return;
    setState(() => _loading = true);
    final results = await widget.onSearch(raw.trim());
    if (!mounted) return;
    final selectedIds = widget.selected.map((e) => e.id).toSet();
    setState(() {
      _loading = false;
      _results = results.where((e) => !selectedIds.contains(e.id)).toList();
    });
  }

  bool get _showCreate {
    if (widget.onCreate == null) return false;
    final q = _ctrl.text.trim();
    if (q.isEmpty || _creating) return false;
    final lower = q.toLowerCase();
    final exactInResults = _results.any((e) => e.label.trim().toLowerCase() == lower);
    final exactSelected = widget.selected.any((e) => e.label.trim().toLowerCase() == lower);
    return !exactInResults && !exactSelected;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(widget.label, style: theme.textTheme.titleSmall),
        const SizedBox(height: 6),
        TextField(
          controller: _ctrl,
          focusNode: _focus,
          enabled: widget.enabled && !_creating,
          decoration: InputDecoration(
            hintText: widget.hint,
            suffixIcon: _loading
                ? const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  )
                : IconButton(
                    icon: const Icon(Icons.search),
                    onPressed: () => _runSearch(_ctrl.text),
                  ),
          ),
          onChanged: (v) {
            setState(() => _open = true);
            if (v.endsWith(' ')) {
              _runSearch(v);
            } else {
              _scheduleSearch(v);
            }
          },
          onTap: () {
            setState(() => _open = true);
            _runSearch(_ctrl.text);
          },
          onSubmitted: (v) => _runSearch(v),
        ),
        if (_open) ...[
          const SizedBox(height: 4),
          Material(
            elevation: 2,
            borderRadius: BorderRadius.circular(12),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 220),
              child: ListView(
                shrinkWrap: true,
                padding: EdgeInsets.zero,
                children: [
                  if (_loading && _results.isEmpty)
                    const ListTile(
                      dense: true,
                      title: Text('…'),
                    ),
                  ..._results.take(25).map(
                    (item) => ListTile(
                      dense: true,
                      title: Text(item.label),
                      onTap: () {
                        widget.onSelect(item.id, item.label);
                        _ctrl.clear();
                        setState(() {
                          _open = false;
                          _results = [];
                        });
                        _focus.unfocus();
                      },
                    ),
                  ),
                  if (!_loading && _results.isEmpty && !_showCreate)
                    ListTile(
                      dense: true,
                      title: Text(
                        _ctrl.text.trim().isEmpty
                            ? (Localizations.localeOf(context).languageCode == 'ar'
                                ? 'اكتب للبحث…'
                                : 'Type to search…')
                            : (Localizations.localeOf(context).languageCode == 'ar'
                                ? 'لا توجد نتائج'
                                : 'No matches'),
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                  if (_showCreate)
                    ListTile(
                      dense: true,
                      title: Text(
                        widget.createLabelBuilder?.call(_ctrl.text.trim()) ??
                            'Add "${_ctrl.text.trim()}"',
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: theme.colorScheme.primary,
                        ),
                      ),
                      trailing: _creating
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.person_add_alt_1),
                      onTap: _creating
                          ? null
                          : () async {
                              final name = _ctrl.text.trim();
                              setState(() => _creating = true);
                              try {
                                await widget.onCreate!(name);
                                if (!mounted) return;
                                _ctrl.clear();
                                setState(() {
                                  _open = false;
                                  _results = [];
                                });
                                _focus.unfocus();
                              } finally {
                                if (mounted) setState(() => _creating = false);
                              }
                            },
                    ),
                ],
              ),
            ),
          ),
        ],
        if (widget.selected.isNotEmpty) ...[
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: widget.selected
                .map(
                  (item) => InputChip(
                    label: Text(item.label.isEmpty ? item.id : item.label),
                    onDeleted: widget.enabled ? () => widget.onRemove(item.id) : null,
                  ),
                )
                .toList(),
          ),
        ],
      ],
    );
  }
}
