import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_service.dart';
import '../config.dart';
import '../models/book.dart';
import '../providers/auth_provider.dart';

String _resolveCoverUrl(String path) {
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  final origin = Uri.parse(apiBaseUrl).origin;
  return path.startsWith('/') ? '$origin$path' : '$origin/$path';
}

class BookDetailScreen extends StatefulWidget {
  const BookDetailScreen({super.key});

  @override
  State<BookDetailScreen> createState() => _BookDetailScreenState();
}

class _BookDetailScreenState extends State<BookDetailScreen> {
  Book? _book;
  bool _loading = true;
  int _qty = 1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final args = ModalRoute.of(context)?.settings.arguments;
    if (args is Book) {
      setState(() {
        _book = args;
        _loading = false;
      });
    } else {
      final id = ModalRoute.of(context)?.settings.arguments as String?;
      if (id != null) _load(id);
    }
  }

  Future<void> _load(String id) async {
    setState(() => _loading = true);
    final res = await ApiService.instance.getBook(id);
    setState(() {
      _loading = false;
      _book = res.data;
    });
  }

  Future<void> _addToCart() async {
    if (_book == null) return;
    final auth = context.read<AuthProvider>();
    if (auth.userType != UserType.customer) {
      Navigator.pushNamed(context, '/login');
      return;
    }
    final res = await ApiService.instance.addToCart(_book!.id, quantity: _qty);
    if (!mounted) return;
    if (res.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Added to cart')),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading || _book == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    final b = _book!;
    return Scaffold(
      appBar: AppBar(title: Text(b.title)),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if ((b.coverImageThumb ?? b.coverImage) != null) ...[
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: GestureDetector(
                  onTap: b.coverImage != null && b.coverImage!.isNotEmpty
                      ? () => _showFullImage(context, b.coverImage!)
                      : null,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.network(
                      _resolveCoverUrl(b.coverImageThumb ?? b.coverImage!),
                      width: 340,
                      height: 480,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                    ),
                  ),
                ),
              ),
            ],
            Text(
              b.title,
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 8),
            Text(
              '\$${b.price.toStringAsFixed(2)}',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                  ),
            ),
            if (b.authors != null && b.authors!.isNotEmpty)
              _buildInfoRow('Authors', b.authors!.map((a) => a.name ?? '').join(', ')),
            if (b.category != null)
              _buildInfoRow('Category', b.category!.subjectTitle ?? b.category!.deweyCode ?? ''),
            if (b.isbn != null && b.isbn!.isNotEmpty)
              _buildInfoRow('ISBN', b.isbn!),
            if (b.publisher != null && b.publisher!.isNotEmpty)
              _buildInfoRow('Publisher', b.publisher!),
            if (b.publishYear != null)
              _buildInfoRow('Year', b.publishYear.toString()),
            if (b.pages != null)
              _buildInfoRow('Pages', b.pages.toString()),
            if (b.editionNumber != null)
              _buildInfoRow('Edition', b.editionNumber.toString()),
            if (b.size != null && b.size!.isNotEmpty)
              _buildInfoRow('Size', b.size!),
            if (b.weight != null)
              _buildInfoRow('Weight', '${b.weight} kg'),
            if (b.stockQuantity >= 0)
              _buildInfoRow(
                'Stock',
                b.stockQuantity > 0 ? '${b.stockQuantity} in stock' : 'Out of stock',
              ),
            if (b.description != null && b.description!.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('Description', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(b.description!, style: Theme.of(context).textTheme.bodyMedium),
            ],
            const SizedBox(height: 24),
            Row(
              children: [
                const Text('Quantity: '),
                IconButton(
                  icon: const Icon(Icons.remove),
                  onPressed: _qty > 1 ? () => setState(() => _qty--) : null,
                ),
                Text('$_qty'),
                IconButton(
                  icon: const Icon(Icons.add),
                  onPressed: () => setState(() => _qty++),
                ),
              ],
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: b.stockQuantity > 0 ? _addToCart : null,
              icon: const Icon(Icons.add_shopping_cart),
              label: const Text('Add to Cart'),
            ),
          ],
        ),
      ),
    );
  }

  void _showFullImage(BuildContext context, String url) {
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.all(16),
        child: Stack(
          children: [
            InteractiveViewer(
              minScale: 0.5,
              maxScale: 4,
              child: Image.network(
                _resolveCoverUrl(url),
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => const Icon(Icons.broken_image, size: 64),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton(
                icon: const Icon(Icons.close, color: Colors.white),
                onPressed: () => Navigator.pop(ctx),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(
              '$label:',
              style: TextStyle(
                fontWeight: FontWeight.w500,
                color: Colors.grey.shade700,
              ),
            ),
          ),
          Expanded(child: Text(value)),
        ],
      ),
    );
  }
}
