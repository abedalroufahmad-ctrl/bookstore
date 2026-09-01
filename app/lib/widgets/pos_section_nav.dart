import 'package:flutter/material.dart';

import '../l10n/app_localizations.dart';

class PosSectionNav extends StatelessWidget {
  const PosSectionNav({super.key, required this.reportsActive});

  final bool reportsActive;

  @override
  Widget build(BuildContext context) {
    final t = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _chip(
            context,
            label: t.adminPosTerminal,
            selected: !reportsActive,
            onTap: reportsActive
                ? () => Navigator.of(context).pushReplacementNamed('/admin/pos')
                : null,
            theme: theme,
          ),
          _chip(
            context,
            label: t.adminPosReports,
            selected: reportsActive,
            onTap: reportsActive
                ? null
                : () => Navigator.of(context).pushReplacementNamed('/admin/pos/reports'),
            theme: theme,
          ),
        ],
      ),
    );
  }

  Widget _chip(
    BuildContext context, {
    required String label,
    required bool selected,
    required VoidCallback? onTap,
    required ThemeData theme,
  }) {
    final bg = selected ? theme.colorScheme.primary : theme.colorScheme.surfaceContainerHighest;
    final fg = selected ? theme.colorScheme.onPrimary : theme.colorScheme.onSurface;
    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Text(label, style: theme.textTheme.labelLarge?.copyWith(color: fg, fontWeight: FontWeight.w600)),
        ),
      ),
    );
  }
}
