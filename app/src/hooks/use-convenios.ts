import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';

import { clearToken } from '@/lib/auth-storage';
import { ApiError } from '@/services/api-client';
import { listarConvenios } from '@/services/convenios';
import type { ConvenioLista, FiltrosConvenios } from '@/types/convenio';

export type EstadoLista = 'cargando' | 'listo' | 'error';

type Filtros = Omit<FiltrosConvenios, 'page'>;

type Datos = {
  /** Filtros con los que se pidieron estos datos. */
  filtros: Filtros;
  items: ConvenioLista[];
  pagina: number;
  ultimaPagina: number;
  total: number;
};

/**
 * Lista paginada de convenios. Cuando cambian los filtros vuelve a la página 1;
 * `cargarMas` pide la página siguiente y la agrega al final (scroll infinito).
 * `filtros` debe estar memorizado (useMemo) para no disparar recargas de más.
 *
 * El estado "cargando" se deriva de comparar los filtros actuales con los de los
 * datos guardados: si no coinciden, todavía no llegó la respuesta del filtro nuevo.
 */
export function useConvenios(filtros: Filtros) {
  const router = useRouter();
  const [datos, setDatos] = useState<Datos | null>(null);
  const [fallo, setFallo] = useState<{ filtros: Filtros; mensaje: string } | null>(null);
  const [errorMas, setErrorMas] = useState<string | null>(null);
  const [cargandoMas, setCargandoMas] = useState(false);
  const [actualizando, setActualizando] = useState(false);

  // Descarta respuestas viejas si el usuario cambia el filtro mientras se carga.
  const peticion = useRef(0);

  const cargar = useCallback(
    async (nroPagina: number, modo: 'inicial' | 'actualizar' | 'agregar') => {
      const id = ++peticion.current;

      try {
        const respuesta = await listarConvenios({ ...filtros, page: nroPagina });
        if (id !== peticion.current) return;

        setDatos((previos) => {
          const base = {
            filtros,
            pagina: respuesta.current_page,
            ultimaPagina: respuesta.last_page,
            total: respuesta.total,
          };

          if (modo !== 'agregar' || !previos || previos.filtros !== filtros) {
            return { ...base, items: respuesta.data };
          }

          const yaEstan = new Set(previos.items.map((c) => c.id_instrumento));
          const nuevos = respuesta.data.filter((c) => !yaEstan.has(c.id_instrumento));
          return { ...base, items: [...previos.items, ...nuevos] };
        });
        setFallo(null);
        setErrorMas(null);
      } catch (causa) {
        if (id !== peticion.current) return;

        if (causa instanceof ApiError && causa.status === 401) {
          await clearToken();
          router.replace('/');
          return;
        }

        const mensaje = causa instanceof Error ? causa.message : 'Ocurrió un error inesperado.';

        // Un fallo al cargar más o al refrescar no tira abajo la lista que ya se ve.
        if (modo === 'inicial') setFallo({ filtros, mensaje });
        else setErrorMas(mensaje);
      } finally {
        if (id === peticion.current) {
          setCargandoMas(false);
          setActualizando(false);
        }
      }
    },
    [filtros, router],
  );

  // Primera carga y cada vez que cambia algún filtro.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- la carga actualiza el estado al resolverse
    void cargar(1, 'inicial');
  }, [cargar]);

  const actual = datos && datos.filtros === filtros ? datos : null;
  const estado: EstadoLista = actual
    ? 'listo'
    : fallo && fallo.filtros === filtros
      ? 'error'
      : 'cargando';

  const cargarMas = useCallback(() => {
    if (!actual || cargandoMas || actualizando || actual.pagina >= actual.ultimaPagina) return;
    setErrorMas(null);
    setCargandoMas(true);
    void cargar(actual.pagina + 1, 'agregar');
  }, [actual, actualizando, cargandoMas, cargar]);

  const actualizar = useCallback(() => {
    setErrorMas(null);
    setActualizando(true);
    void cargar(1, actual ? 'actualizar' : 'inicial');
  }, [actual, cargar]);

  return {
    items: actual?.items ?? [],
    total: actual?.total ?? 0,
    estado,
    error: estado === 'error' ? (fallo?.mensaje ?? null) : errorMas,
    cargandoMas,
    actualizando,
    cargarMas,
    actualizar,
  };
}
