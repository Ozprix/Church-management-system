import { NextResponse } from 'next/server';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';

export async function GET() {
  try {
    const specPath = join(process.cwd(), '..', 'packages', 'contracts', 'openapi', 'church.json');
    const contents = await readFile(specPath, 'utf-8');
    return new NextResponse(contents, {
      status: 200,
      headers: {
        'Content-Type': 'application/json',
      },
    });
  } catch (error) {
    return NextResponse.json(
      { error: 'Unable to load OpenAPI specification.' },
      { status: 500 }
    );
  }
}
